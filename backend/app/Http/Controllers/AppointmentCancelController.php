<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Booking\CancelAppointmentRequest;
use App\Http\Resources\MyAppointmentResource;
use App\Jobs\SendAppointmentCancelledByClientNotification;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AppointmentCancelController extends Controller
{
    /**
     * POST /api/appointments/{id}/cancel
     *
     * Annulation côté client avec délai minimum configurable par salon.
     * Transitions autorisées : pending → cancelled, confirmed → cancelled.
     */
    public function __invoke(CancelAppointmentRequest $request, int $id): MyAppointmentResource|JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $appointment = Appointment::where('id', $id)
            ->with(['company', 'service', 'companyUser.user'])
            ->first();

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        }

        // 403 — ne peut annuler que ses propres RDV
        if ((int) $appointment->user_id !== (int) $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $currentStatus = $appointment->status instanceof AppointmentStatus
            ? $appointment->status
            : AppointmentStatus::from((string) $appointment->status);

        // 422 — statut non annulable
        if (! in_array($currentStatus, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This appointment cannot be cancelled.',
                'errors'  => ['status' => ['not-cancellable-status']],
            ], 422);
        }

        // 422 — délai minimum non respecté
        $company          = $appointment->company;
        $minCancelHours   = (int) ($company->min_cancel_hours ?? 2);
        $startsAt         = $appointment->starts_at;
        $cancellableUntil = $startsAt->copy()->subHours($minCancelHours);

        if ($minCancelHours > 0 && now()->greaterThanOrEqualTo($cancellableUntil)) {
            $locale = $authUser->locale ?? 'sq';
            $msg    = trans('validation.cancel_delay_exceeded', [
                'hours' => $minCancelHours,
                'until' => $cancellableUntil->toIso8601String(),
            ], $locale);

            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors'  => ['delay' => [$msg]],
            ], 422);
        }

        // Transition
        $appointment->update([
            'status'                 => AppointmentStatus::Cancelled,
            'cancelled_by_client_at' => now(),
            'cancellation_reason'    => $request->validated('reason'),
        ]);

        // Notification push fan-out vers owner + employés du salon
        SendAppointmentCancelledByClientNotification::dispatch($appointment->fresh(['service', 'user', 'company']));

        return new MyAppointmentResource($appointment->fresh(['company', 'service', 'companyUser.user']));
    }
}
