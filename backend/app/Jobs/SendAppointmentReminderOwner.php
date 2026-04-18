<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\AppointmentNotificationSent;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminderOwner implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $userId,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt = $this->appointment->load(['service', 'user', 'company']);
        $type = 'appointment.reminder_owner';

        $recipient = User::find($this->userId);

        if (! $recipient) {
            return;
        }

        // Vérification de la préférence "quiet day reminder".
        $pref = $recipient->ensureNotificationPreference();

        if (! $pref->notify_quiet_day_reminder) {
            return;
        }

        // Règle métier : uniquement si le destinataire a ≤ 2 appts ce jour-là.
        // On compte via company_user (appointments assignés à cet employé).
        $date         = $appt->date instanceof \Carbon\Carbon
            ? $appt->date->format('Y-m-d')
            : substr((string) $appt->date, 0, 10);

        $apptCount = Appointment::query()
            ->whereHas('companyUser', fn ($q) => $q->where('user_id', $this->userId))
            ->whereDate('date', $date)
            ->count();

        if ($apptCount > 2) {
            return;
        }

        // Dédup.
        if (AppointmentNotificationSent::alreadySent($appt->id, $recipient->id, $type)) {
            return;
        }

        $clientName = $appt->is_walk_in
            ? trim(($appt->walk_in_first_name ?? '') . ' ' . ($appt->walk_in_last_name ?? ''))
            : ($appt->user
                ? trim($appt->user->first_name . ' ' . $appt->user->last_name)
                : 'Client');

        $time = substr((string) $appt->start_time, 0, 5);

        $fcm->sendToUser(
            user:       $recipient,
            type:       $type,
            data:       [
                'type'          => $type,
                'appointmentId' => (string) $appt->id,
                'companyId'     => (string) $appt->company_id,
            ],
            titleKey:   'appointment_reminder_owner_title',
            bodyKey:    'appointment_reminder_owner_body',
            bodyParams: [
                'client_name'  => $clientName,
                'service_name' => $appt->service?->name ?? '',
                'time'         => $time,
            ],
        );

        AppointmentNotificationSent::markSent($appt->id, $recipient->id, $type);
    }
}
