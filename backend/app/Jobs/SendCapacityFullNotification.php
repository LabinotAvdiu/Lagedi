<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Services\FcmService;
use App\Services\NotificationGate;
use App\Services\NotificationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * C14 — Notifie l'owner quand la capacité d'un salon est atteinte pour une journée.
 *
 * Ce job est déclenché depuis BookingController@store après la création d'un booking
 * confirmé/pending sur un salon capacity_based.
 *
 * La logique de "capacité pleine" est simplifiée ici :
 *   - On compte tous les bookings confirmed+pending pour ce salon ce jour-là.
 *   - Si le count atteint un seuil (CAPACITY_THRESHOLD), on notifie.
 *
 * TODO — Logique capacité précise : pour un calcul exact (max_concurrent × nb_créneaux),
 * une query par service + overlap temporel serait nécessaire. La simplification
 * ci-dessus (seuil absolu) est acceptable pour la v1 et évite de coupler ce job
 * à la table services et au calcul de créneaux.
 *
 * La dedup via ShouldBeUnique garantit qu'une seule notification est envoyée
 * par (company, date) dans la fenêtre de 10 min, même si plusieurs bookings
 * arrivent simultanément.
 */
class SendCapacityFullNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        // Dedup par (company, date) — une notif par journée.
        return 'capacity_full_' . $this->companyId . '_' . $this->date;
    }

    public int $uniqueFor = 600; // 10 min dedup

    /**
     * Seuil de bookings à partir duquel on considère la journée "pleine".
     * Valeur conservative pour la v1 — à affiner une fois la logique
     * max_concurrent × créneaux sera implémentée.
     */
    private const CAPACITY_THRESHOLD = 5;

    public function __construct(
        public readonly int $companyId,
        public readonly string $date,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $type = 'capacity.full';

        $company = Company::find($this->companyId);

        if (! $company) {
            return;
        }

        // Vérifie que le salon est bien en mode capacity_based.
        $mode = $company->booking_mode instanceof BookingMode
            ? $company->booking_mode
            : BookingMode::from((string) $company->booking_mode);

        if ($mode !== BookingMode::CapacityBased) {
            return;
        }

        // Compte les bookings actifs pour ce jour.
        $count = Appointment::where('company_id', $this->companyId)
            ->where('date', $this->date)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ])
            ->count();

        if ($count < self::CAPACITY_THRESHOLD) {
            return;
        }

        // Récupère l'owner.
        $ownerPivot = CompanyUser::where('company_id', $this->companyId)
            ->where('role', CompanyRole::Owner->value)
            ->where('is_active', true)
            ->with(['user.devices'])
            ->first();

        if (! $ownerPivot?->user) {
            return;
        }

        $owner = $ownerPivot->user;

        if ($owner->devices()->count() === 0) {
            return;
        }

        // D19 — Opt-out preference
        if (! $owner->isNotificationEnabled('push', NotificationType::CAPACITY_FULL)) {
            Log::info('[FCM] capacity.full skipped — user opted out', ['owner_id' => $owner->id]);
            return;
        }

        // D21 — Quiet hours
        if (! NotificationGate::respectsQuietHours($owner, NotificationType::CAPACITY_FULL)) {
            $delay = NotificationGate::nextAllowedAt($owner);
            Log::info('[FCM] capacity.full deferred — quiet hours', ['owner_id' => $owner->id, 'retry_at' => $delay]);
            self::dispatch($this->companyId, $this->date)->delay($delay);
            return;
        }

        // D22 — Dedup 10 min (company + date)
        $refKey = 'company_' . $this->companyId . '_' . $this->date;
        if (NotificationGate::isDuplicate($owner, NotificationType::CAPACITY_FULL, $refKey)) {
            Log::warning('[FCM] capacity.full blocked — duplicate', ['owner_id' => $owner->id, 'date' => $this->date]);
            return;
        }

        // D23 — Frequency cap
        if (NotificationGate::exceedsFrequencyCap($owner)) {
            Log::info('[FCM] capacity.full blocked — frequency cap', ['owner_id' => $owner->id]);
            return;
        }

        // Formatte la date pour l'affichage (DD/MM/YYYY).
        $displayDate = \Carbon\Carbon::parse($this->date)->format('d/m/Y');

        $fcm->sendToUser(
            user:       $owner,
            type:       $type,
            data:       [
                'type'      => $type,
                'companyId' => (string) $this->companyId,
                'date'      => $this->date,
            ],
            titleKey:   'capacity_full_title',
            bodyKey:    'capacity_full_body',
            bodyParams: ['date' => $displayDate],
        );

        Log::info('[FCM] capacity.full dispatched', [
            'company_id'    => $this->companyId,
            'date'          => $this->date,
            'booking_count' => $count,
            'owner_id'      => $owner->id,
        ]);

        // D20 — Log
        NotificationLogger::log(
            user: $owner,
            channel: 'push',
            type: NotificationType::CAPACITY_FULL,
            payload: ['companyId' => $this->companyId, 'date' => $this->date],
            refType: 'company',
            refId: $this->companyId,
        );
    }
}
