<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
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
 * C12 — Demande d'avis envoyée au client J+1 à 18h.
 *
 * Ce job est schedulé depuis SendAppointmentConfirmedNotification::handle()
 * avec un delay de now()->addDay()->setHour(18)->setMinute(0)->setSecond(0).
 *
 * Garde anti-doublon intégrée : si un avis existe déjà pour ce RDV,
 * ou si le RDV a été annulé/rejeté, le job s'arrête sans envoyer.
 *
 * TODO — Opt-out : implémenter une table/colonne `notif_prefs.review_requests`
 * et vérifier `$client->notifPrefs->review_requests !== false` avant l'envoi.
 */
class SendReviewRequestNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        return 'review_request_' . $this->appointment->id;
    }

    public int $uniqueFor = 600; // 10 min dedup

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        // Recharge le RDV pour avoir l'état frais au moment de l'exécution.
        $appt = $this->appointment->fresh(['user', 'company', 'review']);

        if (! $appt) {
            return;
        }

        $type   = 'review.request';
        $client = $appt->user;

        // Walk-in sans compte — pas de notification.
        if (! $client) {
            return;
        }

        // D19 — Opt-out preference
        if (! $client->isNotificationEnabled('push', NotificationType::REVIEW_REQUEST)) {
            Log::info('[FCM] review_request skipped — user opted out', ['client_id' => $client->id]);
            return;
        }

        // D21 — Quiet hours
        if (! NotificationGate::respectsQuietHours($client, NotificationType::REVIEW_REQUEST)) {
            $delay = NotificationGate::nextAllowedAt($client);
            Log::info('[FCM] review_request deferred — quiet hours', ['client_id' => $client->id, 'retry_at' => $delay]);
            self::dispatch($appt)->delay($delay);
            return;
        }

        // D22 — Dedup 10 min
        $refKey = 'appt_' . $appt->id;
        if (NotificationGate::isDuplicate($client, NotificationType::REVIEW_REQUEST, $refKey)) {
            Log::warning('[FCM] review_request blocked — duplicate', ['client_id' => $client->id, 'appt_id' => $appt->id]);
            return;
        }

        // D23 — Frequency cap
        if (NotificationGate::exceedsFrequencyCap($client)) {
            Log::info('[FCM] review_request blocked — frequency cap', ['client_id' => $client->id]);
            return;
        }

        // Vérifie que le RDV est toujours actif (confirmed ou completed).
        $status = $appt->status instanceof AppointmentStatus
            ? $appt->status
            : AppointmentStatus::from((string) $appt->status);

        $allowedStatuses = [AppointmentStatus::Confirmed, AppointmentStatus::Completed];
        if (! in_array($status, $allowedStatuses, true)) {
            Log::info('[FCM] review_request skipped — appointment not active', [
                'appointment_id' => $appt->id,
                'status'         => $status->value,
            ]);
            return;
        }

        // Déjà reviewé — pas de relance.
        if ($appt->review !== null) {
            Log::info('[FCM] review_request skipped — already reviewed', [
                'appointment_id' => $appt->id,
            ]);
            return;
        }

        if ($client->devices()->count() === 0) {
            return;
        }

        $salonName = $appt->company?->name ?? 'sallonit';

        $fcm->sendToUser(
            user:       $client,
            type:       $type,
            data:       [
                'type'          => $type,
                'appointmentId' => (string) $appt->id,
                'companyId'     => (string) $appt->company_id,
            ],
            titleKey:   'review_request_title',
            bodyKey:    'review_request_body',
            bodyParams: ['salon_name' => $salonName],
        );

        Log::info('[FCM] review.request dispatched', [
            'appointment_id' => $appt->id,
            'client_id'      => $client->id,
        ]);

        // D20 — Log
        NotificationLogger::log(
            user: $client,
            channel: 'push',
            type: NotificationType::REVIEW_REQUEST,
            payload: ['titleKey' => 'review_request_title', 'salonName' => $salonName],
            refType: 'appointment',
            refId: $appt->id,
        );
    }
}
