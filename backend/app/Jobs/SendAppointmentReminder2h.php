<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\AppointmentNotificationSent;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    }
}
