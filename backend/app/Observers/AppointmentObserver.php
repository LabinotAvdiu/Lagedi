<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentCancelledByOwnerNotification;
use App\Jobs\SendAppointmentConfirmedNotification;
use App\Jobs\SendAppointmentCreatedNotification;
use App\Jobs\SendAppointmentRejectedNotification;
use App\Models\Appointment;

class AppointmentObserver
{
    /**
     * Déclenché après la création d'un rendez-vous.
     * Walk-ins sans user_id sont inclus (le job filtre les destinataires pro).
     */
    public function created(Appointment $appointment): void
    {
        SendAppointmentCreatedNotification::dispatch($appointment);
    }

    /**
     * Déclenché après la mise à jour d'un rendez-vous.
     * On n'envoie que si le statut a réellement changé.
     */
    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged('status')) {
            return;
        }

        $newStatus = $appointment->status instanceof AppointmentStatus
            ? $appointment->status
            : AppointmentStatus::from((string) $appointment->status);

        // Previous status — raw string, may be an enum value or null for
        // freshly-created rows (impossible here since we're in updated()).
        $previousRaw = $appointment->getOriginal('status');
        $previous = $previousRaw instanceof AppointmentStatus
            ? $previousRaw
            : ($previousRaw !== null
                ? AppointmentStatus::from((string) $previousRaw)
                : null);

        match ($newStatus) {
            AppointmentStatus::Confirmed =>
                SendAppointmentConfirmedNotification::dispatch($appointment),
            AppointmentStatus::Rejected  =>
                SendAppointmentRejectedNotification::dispatch($appointment),
            AppointmentStatus::Cancelled => $this->handleCancelled(
                $appointment, $previous,
            ),
            default => null,
        };
    }

    /**
     * Cancelled transitions split by who triggered it:
     *   • Client self-cancel → the AppointmentCancelController already
     *     dispatched its own "by-client" notif; we skip here.
     *     Detected via `cancelled_by_client_at`.
     *   • Owner cancels a pending/confirmed appointment → fire the
     *     "by-owner" notification to the client.
     *   • Owner frees a slot after a prior refusal (rejected → cancelled) →
     *     silent: the original refusal notification already reached the
     *     client, they don't need a second push.
     */
    private function handleCancelled(
        Appointment $appointment,
        ?AppointmentStatus $previous,
    ): void {
        // rejected → cancelled = "free the slot", silent
        if ($previous === AppointmentStatus::Rejected) {
            return;
        }

        // Client-initiated cancellation → the controller handles its notif
        if ($appointment->cancelled_by_client_at !== null) {
            return;
        }

        SendAppointmentCancelledByOwnerNotification::dispatch($appointment);
    }
}
