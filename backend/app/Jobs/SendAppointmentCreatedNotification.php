<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CompanyRole;
use App\Models\Appointment;
use App\Models\AppointmentNotificationSent;
use App\Models\CompanyUser;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentCreatedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt    = $this->appointment->load(['service', 'user', 'company']);
        $type    = 'appointment.created';
        $service = $appt->service;

        // Récupère owner + tous les employees actifs du salon concerné.
        $companyUsers = CompanyUser::query()
            ->where('company_id', $appt->company_id)
            ->whereIn('role', [CompanyRole::Owner->value, CompanyRole::Employee->value])
            ->where('is_active', true)
            ->with(['user.devices', 'user.notificationPreference'])
            ->get();

        // Nom du client à afficher dans la notification.
        $clientName = $appt->is_walk_in
            ? trim(($appt->walk_in_first_name ?? '') . ' ' . ($appt->walk_in_last_name ?? ''))
            : ($appt->user
                ? trim($appt->user->first_name . ' ' . $appt->user->last_name)
                : 'Client');

        $time = substr((string) $appt->start_time, 0, 5); // HH:MM

        foreach ($companyUsers as $cu) {
            $recipient = $cu->user;

            if (! $recipient) {
                continue;
            }

            // Vérification de la préférence utilisateur.
            $pref = $recipient->ensureNotificationPreference();

            if (! $pref->notify_new_booking) {
                continue;
            }

            // Dédup.
            if (AppointmentNotificationSent::alreadySent($appt->id, $recipient->id, $type)) {
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
                titleKey:   'appointment_created_title',
                bodyKey:    'appointment_created_body',
                bodyParams: [
                    'client_name'  => $clientName,
                    'service_name' => $service?->name ?? '',
                    'time'         => $time,
                ],
            );

            AppointmentNotificationSent::markSent($appt->id, $recipient->id, $type);
        }
    }
}
