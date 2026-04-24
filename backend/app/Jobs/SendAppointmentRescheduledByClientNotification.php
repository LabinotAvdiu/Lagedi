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
use Illuminate\Support\Facades\Log;

/**
 * C11 — Notifie l'owner ET l'employé assigné quand le client déplace son RDV.
 *
 * TODO (trigger manquant) : L'endpoint client permettant de reschedule un RDV
 * n'existe pas encore côté API. Ce job est prêt à être câblé dès que l'endpoint
 * sera implémenté (probablement PATCH /api/bookings/{id}/reschedule).
 * Dispatcher avec : SendAppointmentRescheduledByClientNotification::dispatch($appointment)
 */
class SendAppointmentRescheduledByClientNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        return 'reschedule_client_' . $this->appointment->id;
    }

    public int $uniqueFor = 600; // 10 min dedup

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt = $this->appointment->load(['user', 'company', 'service', 'companyUser.user']);
        $type = 'appointment.rescheduled_by_client';

        $clientName = $appt->user
            ? trim($appt->user->first_name . ' ' . $appt->user->last_name)
            : 'Client';

        $newDate = $appt->date->format('d/m/Y');
        $newTime = substr((string) $appt->start_time, 0, 5);

        $recipients = [];

        // Owner du salon.
        $ownerPivot = CompanyUser::where('company_id', $appt->company_id)
            ->where('role', CompanyRole::Owner->value)
            ->where('is_active', true)
            ->with(['user.devices'])
            ->first();

        if ($ownerPivot?->user) {
            $recipients[] = $ownerPivot->user;
        }

        // Employé assigné (si différent de l'owner).
        if ($appt->companyUser?->user) {
            $employee = $appt->companyUser->user;
            // Évite le doublon si l'owner est aussi l'employé assigné.
            $alreadyAdded = collect($recipients)->pluck('id')->contains($employee->id);
            if (! $alreadyAdded) {
                $recipients[] = $employee;
            }
        }

        foreach ($recipients as $recipient) {
            if ($recipient->devices()->count() === 0) {
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
                titleKey:   'appointment_rescheduled_by_client_title',
                bodyKey:    'appointment_rescheduled_by_client_body',
                bodyParams: [
                    'client_name' => $clientName,
                    'new_date'    => $newDate,
                    'new_time'    => $newTime,
                ],
            );
        }

        Log::info('[FCM] appointment.rescheduled_by_client dispatched', [
            'appointment_id'   => $appt->id,
            'recipient_count'  => count($recipients),
        ]);
    }
}
