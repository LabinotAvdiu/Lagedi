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

class SendAppointmentConfirmedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt   = $this->appointment->load(['service', 'user', 'company']);
        $type   = 'appointment.confirmed';
        $client = $appt->user;

        // Walk-in sans user_id associé — pas de notification client.
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
            titleKey:   'appointment_confirmed_title',
            bodyKey:    'appointment_confirmed_body',
            bodyParams: [
                'service_name' => $appt->service?->name ?? '',
                'time'         => $time,
            ],
        );

        AppointmentNotificationSent::markSent($appt->id, $client->id, $type);
    }
}
