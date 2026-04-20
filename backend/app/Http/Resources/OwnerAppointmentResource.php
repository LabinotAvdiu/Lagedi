<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appointment shape for the owner's planning view.
 *
 * Requires eager-loaded relations: service, companyUser.user, user (for registered clients).
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
            $employeeName = trim($u->first_name . ' ' . $u->last_name) ?: null;
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

        return [
            'id'                => (string) $this->id,
            'date'              => $this->date->format('Y-m-d'),
            'startTime'         => substr((string) $this->start_time, 0, 5), // "HH:MM"
            'endTime'           => substr((string) $this->end_time, 0, 5),
            'status'            => $this->status instanceof AppointmentStatus
                ? $this->status->value
                : $this->status,
            'clientFirstName'   => $clientFirstName,
            'clientLastName'    => $clientLastName,
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
        ];
    }
}
