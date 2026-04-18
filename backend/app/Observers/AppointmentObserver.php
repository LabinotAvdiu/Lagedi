<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AppointmentStatus;
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

        match ($newStatus) {
            AppointmentStatus::Confirmed => SendAppointmentConfirmedNotification::dispatch($appointment),
            AppointmentStatus::Rejected  => SendAppointmentRejectedNotification::dispatch($appointment),
            default                      => null,
        };
    }
}
