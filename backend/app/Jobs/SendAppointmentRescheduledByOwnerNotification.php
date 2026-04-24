<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * C10 — Notifie le client quand l'owner déplace son RDV.
 *
 * Les anciennes valeurs (oldDate, oldTime) sont passées au constructeur
 * car elles ne sont plus disponibles sur le modèle après la mise à jour.
 */
class SendAppointmentRescheduledByOwnerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        return 'reschedule_owner_' . $this->appointment->id;
    }

    public int $uniqueFor = 600; // 10 min dedup

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $oldDate,
        public readonly string $oldTime,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt   = $this->appointment->load(['user', 'company', 'service']);
        $type   = 'appointment.rescheduled_by_owner';
        $client = $appt->user;

        // Walk-in sans compte — pas de notification.
        if (! $client) {
            return;
        }

        if ($client->devices()->count() === 0) {
            return;
        }

        $oldDatetime = $this->oldDate . ' ' . substr($this->oldTime, 0, 5);
        $newDatetime = $appt->date->format('Y-m-d') . ' ' . substr((string) $appt->start_time, 0, 5);

        $fcm->sendToUser(
            user:       $client,
            type:       $type,
            data:       [
                'type'          => $type,
                'appointmentId' => (string) $appt->id,
                'companyId'     => (string) $appt->company_id,
                'oldDatetime'   => $oldDatetime,
                'newDatetime'   => $newDatetime,
            ],
            titleKey:   'appointment_rescheduled_by_owner_title',
            bodyKey:    'appointment_rescheduled_by_owner_body',
            bodyParams: [
                'old_time' => substr($this->oldTime, 0, 5),
                'new_time' => substr((string) $appt->start_time, 0, 5),
            ],
        );

        Log::info('[FCM] appointment.rescheduled_by_owner dispatched', [
            'appointment_id' => $appt->id,
            'client_id'      => $client->id,
            'old_datetime'   => $oldDatetime,
            'new_datetime'   => $newDatetime,
        ]);
    }
}
