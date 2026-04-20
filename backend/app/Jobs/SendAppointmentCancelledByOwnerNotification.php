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

/**
 * Push notification sent to the client when the owner cancels the booking
 * (either an accepted or a still-pending one). Distinct from the
 * refusal notification: here the slot was approved/pending and the owner
 * then chose to drop it — the client gets a single notification.
 *
 * NOT fired for the "free slot after refusal" flow (rejected → cancelled):
 * the client already received the original rejection notif and doesn't
 * need a second message.
 */
class SendAppointmentCancelledByOwnerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt   = $this->appointment->load(['service', 'user', 'company']);
        $type   = 'appointment.cancelled_by_owner';
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
            titleKey:   'appointment_cancelled_by_owner_title',
            bodyKey:    'appointment_cancelled_by_owner_body',
            bodyParams: [
                'service_name' => $appt->service?->name ?? '',
                'time'         => $time,
                'company_name' => $appt->company?->name ?? '',
            ],
        );

        AppointmentNotificationSent::markSent($appt->id, $client->id, $type);
    }
}
