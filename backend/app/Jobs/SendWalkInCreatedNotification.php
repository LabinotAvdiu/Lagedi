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
 * C9 — Notifie l'owner uniquement quand un employé crée un walk-in.
 *
 * Ne doit PAS être dispatché si c'est l'owner lui-même qui crée le walk-in.
 * Cette garde est appliquée dans le trigger (storeWalkIn) — le job suppose
 * qu'il est dispatché uniquement depuis un contexte employé.
 */
class SendWalkInCreatedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        return 'walkin_' . $this->appointment->id;
    }

    public int $uniqueFor = 600; // 10 min dedup

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt = $this->appointment->load(['companyUser.user', 'company']);
        $type = 'appointment.walk_in_created';

        // Récupère l'owner du salon.
        $ownerPivot = CompanyUser::where('company_id', $appt->company_id)
            ->where('role', CompanyRole::Owner->value)
            ->where('is_active', true)
            ->with(['user.devices'])
            ->first();

        if (! $ownerPivot || ! $ownerPivot->user) {
            return;
        }

        $owner = $ownerPivot->user;

        if ($owner->devices()->count() === 0) {
            return;
        }

        // Nom de l'employé qui a créé le walk-in.
        $employeeName = $appt->companyUser?->user
            ? trim($appt->companyUser->user->first_name . ' ' . $appt->companyUser->user->last_name)
            : 'Punonjësi';

        // Nom du client walk-in.
        $clientName = trim(($appt->walk_in_first_name ?? '') . ' ' . ($appt->walk_in_last_name ?? ''));
        if ($clientName === '') {
            $clientName = 'Walk-in';
        }

        $time = substr((string) $appt->start_time, 0, 5); // HH:MM

        $fcm->sendToUser(
            user:       $owner,
            type:       $type,
            data:       [
                'type'          => $type,
                'appointmentId' => (string) $appt->id,
                'companyId'     => (string) $appt->company_id,
            ],
            titleKey:   'walk_in_created_title',
            bodyKey:    'walk_in_created_body',
            bodyParams: [
                'employee_name' => $employeeName,
                'client_name'   => $clientName,
                'time'          => $time,
            ],
        );

        Log::info('[FCM] appointment.walk_in_created dispatched', [
            'appointment_id' => $appt->id,
            'company_id'     => $appt->company_id,
            'owner_id'       => $owner->id,
        ]);
    }
}
