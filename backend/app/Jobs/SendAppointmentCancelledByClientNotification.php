<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CompanyRole;
use App\Models\Appointment;
use App\Models\CompanyUser;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fan-out notification vers owner + employés actifs du salon quand un client annule.
 * Pas de dédup via AppointmentNotificationSent — l'annulation ne se produit qu'une fois.
 */
class SendAppointmentCancelledByClientNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt = $this->appointment->load(['service', 'user', 'company']);
        $type = 'appointment.cancelled_by_client';

        $companyUsers = CompanyUser::query()
            ->where('company_id', $appt->company_id)
            ->whereIn('role', [CompanyRole::Owner->value, CompanyRole::Employee->value])
            ->where('is_active', true)
            ->with(['user.devices'])
            ->get();

        $clientName = $appt->user
            ? trim($appt->user->first_name . ' ' . $appt->user->last_name)
            : 'Client';

        $time = substr((string) $appt->start_time, 0, 5); // HH:MM

        foreach ($companyUsers as $cu) {
            $recipient = $cu->user;

            if (! $recipient) {
                continue;
            }

            $fcm->sendToUser(
                user:       $recipient,
                type:       $type,
                data:       [
                    'type'          => $type,
                    'appointmentId' => (string) $appt->id,
                    'companyId'     => (string) $appt->company_id,
                ],
                titleKey:   'appointment_cancelled_by_client_title',
                bodyKey:    'appointment_cancelled_by_client_body',
                bodyParams: [
                    'client_name'  => $clientName,
                    'service_name' => $appt->service?->name ?? '',
                    'time'         => $time,
                ],
            );
        }
    }
}
