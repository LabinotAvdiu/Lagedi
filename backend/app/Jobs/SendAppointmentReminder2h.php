<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\AppointmentNotificationSent;
use App\Services\FcmService;
use App\Services\NotificationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminder2h implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt   = $this->appointment->load(['service', 'user', 'company']);
        $type   = 'appointment.reminder_2h';
        $client = $appt->user;

        if (! $client) {
            return;
        }

        // D19 — Opt-out preference
        if (! $client->isNotificationEnabled('push', NotificationType::REMINDER_2H)) {
            Log::info('[FCM] reminder_2h skipped — user opted out', ['client_id' => $client->id]);
            return;
        }

        if (AppointmentNotificationSent::alreadySent($appt->id, $client->id, $type)) {
            return;
        }

        $time = substr((string) $appt->start_time, 0, 5);

        $fcm->sendToUser(
            user:       $client,
            type:       $type,
            data:       [
                'type'          => $type,
                'appointmentId' => (string) $appt->id,
                'companyId'     => (string) $appt->company_id,
            ],
            titleKey:   'appointment_reminder_2h_title',
            bodyKey:    'appointment_reminder_2h_body',
            bodyParams: [
                'time' => $time,
            ],
        );

        AppointmentNotificationSent::markSent($appt->id, $client->id, $type);

        // D20 — Log
        NotificationLogger::log(
            user: $client,
            channel: 'push',
            type: NotificationType::REMINDER_2H,
            payload: ['time' => $time],
            refType: 'appointment',
            refId: $appt->id,
        );
    }
}
