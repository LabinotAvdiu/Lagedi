<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Models\Appointment;
use App\Models\CompanyUser;
use App\Support\NameFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appointment shape for the owner's planning view.
 *
 * Requires eager-loaded relations: service, companyUser.user, user (for registered clients).
 *
 * Capability flags (`can.*`) — see docs/PLANNING_CONTRACT.md :
 *   Encapsulent la logique métier (rôle × mode × statut × temps). Le frontend
 *   doit rendre les boutons si le flag est true et rien d'autre. Toute règle
 *   supplémentaire (« owner peut annuler un walk-in mais pas un RDV client »)
 *   vit ici, pas dans l'UI.
 *
 * Additional fields injectés par le controller :
 *   - noShowCounts  : Map<clientId, count> pré-calculée (Feature 4)
 *   - viewerRole    : 'owner' | 'employee' — source de vérité pour les can.*
 *   - bookingMode   : 'capacity_based' | 'employee_based'
 *
 * Feature 4 — clientNoShowCount :
 *   Le controller peut injecter les counts pré-calculés via le `additional` du
 *   ResourceCollection (OwnerAppointmentResource::collection($data)->additional(['noShowCounts' => $map])).
 *   Dans ce cas, la resource lit dans $this->additional.
 *   Si non fourni (ex: storeWalkIn renvoie une resource seule), on fait un COUNT() unique.
 *   Walk-in sans user_id => null.
 */
class OwnerAppointmentResource extends JsonResource
{
    /**
     * Compute the capability flags for this appointment given a viewer role
     * and the company's booking mode.
     *
     * @param  'owner'|'employee'|null  $role
     * @param  'capacity_based'|'employee_based'|null  $mode
     * @return array<string, bool>
     */
    private function capabilities(?string $role, ?string $mode): array
    {
        $status = $this->status instanceof AppointmentStatus
            ? $this->status->value
            : (string) $this->status;

        // Build a concrete end DateTime so "is past" reasoning is unambiguous.
        $endDt = Carbon::parse(
            $this->date->format('Y-m-d') . ' ' . $this->end_time,
        );
        $startDt = Carbon::parse(
            $this->date->format('Y-m-d') . ' ' . $this->start_time,
        );
        $now = Carbon::now();

        $isPast          = $now->gt($endDt);
        $isFuture        = $now->lt($startDt);
        $isWithin24hPast = $endDt->diffInHours($now, false) <= 24 && $isPast;

        // Pending RDVs can only exist in capacity_based. Employees never get
        // to approve — even in capacity mode owners do the approving.
        $canApprove = $role === 'owner'
            && $mode === 'capacity_based'
            && $status === AppointmentStatus::Pending->value
            && ! $isPast;

        // Cancel rules (spec PLANNING_CONTRACT) :
        //   - walk-in manuel + non passé  → bouton Annuler (le pro a créé la
        //     réservation, il peut la retirer sans notification)
        //   - RDV client                  → PAS de bouton côté pro. Le client
        //     est seul maître de sa réservation (le pro a accept/reject en
        //     capacité, rien en individuel — le RDV est déjà confirmed)
        $canCancel = $role !== null
            && ! $isPast
            && (bool) $this->is_walk_in
            && in_array($status, [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ], true);

        // No-show : RDV confirmé passé, dans la fenêtre 24h. Owner + employé.
        $canMarkNoShow = $role !== null
            && $isWithin24hPast
            && $status === AppointmentStatus::Confirmed->value;

        // Free-slot : uniquement après un rejet, pour libérer la capacité.
        $canFreeSlot = $role === 'owner'
            && $mode === 'capacity_based'
            && $status === AppointmentStatus::Rejected->value;

        return [
            'accept'      => $canApprove,
            'reject'      => $canApprove,
            'cancel'      => $canCancel,
            'markNoShow'  => $canMarkNoShow,
            'freeSlot'    => $canFreeSlot,
        ];
    }

    public function toArray(Request $request): array
    {
        // Resolve client identity: walk-in fields take priority over user relation
        if ($this->is_walk_in) {
            $clientFirstName = $this->walk_in_first_name;
            $clientLastName  = $this->walk_in_last_name;
            $clientPhone     = $this->walk_in_phone;
        } else {
            $clientFirstName = $this->user?->first_name;
            $clientLastName  = $this->user?->last_name;
            $clientPhone     = $this->user?->phone;
        }

        // Resolve employee name from companyUser → user (null for Type 2)
        $employeeName = null;
        if ($this->companyUser && $this->companyUser->user) {
            $u = $this->companyUser->user;
            $first = NameFormatter::titleCase($u->first_name);
            $last  = NameFormatter::titleCase($u->last_name);
            $employeeName = trim(($first ?? '') . ' ' . ($last ?? '')) ?: null;
        }

        // Feature 4 — clientNoShowCount (tous salons confondus)
        // Priorité : counts pré-calculés injectés via ->additional(['noShowCounts' => [...]])
        // Fallback  : COUNT() individuel (cas storeWalkIn / resource seule)
        $clientNoShowCount = null;
        if ($this->user_id !== null) {
            $precomputed = $this->additional['noShowCounts'] ?? null;

            if (is_array($precomputed) && array_key_exists($this->user_id, $precomputed)) {
                $clientNoShowCount = (int) $precomputed[$this->user_id];
            } else {
                $clientNoShowCount = Appointment::where('user_id', $this->user_id)
                    ->where('status', AppointmentStatus::NoShow->value)
                    ->count();
            }
        }

        // Viewer context — prefer the controller-injected request attributes
        // (cheap path, the controller batches the role lookup once). Fall back
        // to resolving from the authenticated user's pivot on this company so
        // single-resource responses (e.g. storeWalkIn) still get correct can.*.
        $viewerRole  = $request->attributes->get('viewerRole');
        $bookingMode = $request->attributes->get('bookingMode');

        if ($viewerRole === null || $bookingMode === null) {
            $userId = auth()->id();
            $companyId = $this->company_id;
            if ($userId !== null && $companyId !== null) {
                $pivot = CompanyUser::where('user_id', $userId)
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->first();
                if ($pivot !== null) {
                    $role = $pivot->role instanceof CompanyRole
                        ? $pivot->role->value
                        : (string) $pivot->role;
                    $viewerRole ??= $role === CompanyRole::Owner->value
                        ? 'owner'
                        : 'employee';
                    $bookingMode ??= $pivot->company?->booking_mode instanceof BookingMode
                        ? $pivot->company->booking_mode->value
                        : (string) ($pivot->company?->booking_mode ?? 'employee_based');
                }
            }
        }

        return [
            'id'                => (string) $this->id,
            'date'              => $this->date->format('Y-m-d'),
            'startTime'         => substr((string) $this->start_time, 0, 5), // "HH:MM"
            'endTime'           => substr((string) $this->end_time, 0, 5),
            'status'            => $this->status instanceof AppointmentStatus
                ? $this->status->value
                : $this->status,
            'clientFirstName'   => NameFormatter::titleCase($clientFirstName),
            'clientLastName'    => NameFormatter::titleCase($clientLastName),
            'clientPhone'       => $clientPhone,
            'clientUserId'      => $this->user_id ? (string) $this->user_id : null,
            'clientNoShowCount' => $clientNoShowCount,
            'service'           => $this->service ? [
                'id'              => (string) $this->service->id,
                'name'            => $this->service->name,
                'durationMinutes' => (int) $this->service->duration,
                'price'           => (float) $this->service->price,
            ] : null,
            'employeeName' => $employeeName,
            'isWalkIn'     => (bool) $this->is_walk_in,
            // Cancellation metadata — populated when the client cancelled the
            // booking themselves. The owner needs to know WHY the client
            // cancelled (shown on the cancelled appointment detail).
            'cancellationReason'   => $this->cancellation_reason,
            'cancelledByClientAt'  => $this->cancelled_by_client_at?->toIso8601String(),
            // Owner-side refusal motif, shown in the planning detail and on
            // the client's rejected-appointment card.
            'rejectionReason'      => $this->rejection_reason,
            'rejectedByOwnerAt'    => $this->rejected_by_owner_at?->toIso8601String(),
            // Capability flags — see PLANNING_CONTRACT.md. The front renders
            // buttons iff the flag is true; no role/mode logic in the UI.
            'can' => $this->capabilities($viewerRole, $bookingMode),
        ];
    }
}
