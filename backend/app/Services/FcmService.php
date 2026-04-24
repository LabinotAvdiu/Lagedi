<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\NotificationLogger;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * Service FCM — Fan-out push notifications vers tous les devices d'un utilisateur.
 *
 * Règles métier importantes :
 * - Les notifications configurables (appointment.created, appointment.reminder_owner)
 *   sont filtrées EN AMONT par les jobs ; ce service envoie sans condition.
 * - La locale du destinataire est chargée depuis `users.locale`, fallback 'sq'.
 * - Si FCM_PROJECT_ID n'est pas configuré (env de dev), les envois sont loggués
 *   uniquement — aucune exception n'est levée.
 * - Les tokens qui retournent UNREGISTERED sont supprimés automatiquement.
 */
class FcmService
{
    public function __construct(
        private readonly ?Messaging $messaging = null,
    ) {}

    /**
     * Envoie une notification push à tous les devices d'un utilisateur.
     *
     * @param User   $user       Destinataire
     * @param string $type       Type de notification (ex: "appointment.created")
     * @param array  $data       Données FCM (appointmentId, companyId…)
     * @param string $titleKey   Clé dans resources/lang/{locale}/notifications.php
     * @param string $bodyKey    Clé dans resources/lang/{locale}/notifications.php
     * @param array  $bodyParams Placeholders pour la traduction (:client_name, etc.)
     */
    public function sendToUser(
        User $user,
        string $type,
        array $data,
        string $titleKey,
        string $bodyKey,
        array $bodyParams = [],
    ): void {
        $locale = $user->locale ?? 'sq';

        $title = trans("notifications.{$titleKey}", $bodyParams, $locale);
        $body  = trans("notifications.{$bodyKey}", $bodyParams, $locale);

        $devices = $user->devices()->get();

        // Trace dans notifications_log dès qu'on essaie d'envoyer — même si
        // aucun device n'est présent ni la config FCM absente. C'est cet
        // historique qui alimente l'inbox "Mes notifications" côté app.
        //
        // On extrait ref_type/ref_id du payload pour que l'inbox puisse
        // regrouper par resource (appointment / review / …) plus tard.
        [$refType, $refId] = $this->extractRef($data);
        NotificationLogger::log(
            $user,
            channel: 'push',
            type: $type,
            payload: array_merge(['title' => $title, 'body' => $body], $data),
            refType: $refType,
            refId: $refId,
        );

        if ($devices->isEmpty()) {
            return;
        }

        // Mode dev : pas de credentials FCM configurés — log uniquement.
        if (! $this->isFcmConfigured()) {
            Log::info('[FCM-DEV] Would send notification', [
                'user_id'  => $user->id,
                'type'     => $type,
                'title'    => $title,
                'body'     => $body,
                'data'     => $data,
                'devices'  => $devices->pluck('token')->toArray(),
            ]);

            return;
        }

        foreach ($devices as $device) {
            $this->sendToToken($device, $type, $data, $title, $body);
        }
    }

    /**
     * Extrait (ref_type, ref_id) du payload pour alimenter notifications_log.
     * Retourne [null, null] si aucune resource identifiable.
     */
    private function extractRef(array $data): array
    {
        if (isset($data['appointmentId']) && $data['appointmentId'] !== '') {
            return ['appointment', (int) $data['appointmentId']];
        }
        if (isset($data['reviewId']) && $data['reviewId'] !== '') {
            return ['review', (int) $data['reviewId']];
        }
        if (isset($data['ticketId']) && $data['ticketId'] !== '') {
            return ['support_ticket', (int) $data['ticketId']];
        }
        return [null, null];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function sendToToken(
        UserDevice $device,
        string $type,
        array $data,
        string $title,
        string $body,
    ): void {
        try {
            // kreait/firebase-php 7.x removed the generic `withTarget` helper;
            // the supported path is `CloudMessage::new()->toToken(...)`.
            //
            // We duplicate title/body in the `data` block so the Flutter web
            // foreground handler can always read them, even when Chrome's FCM
            // SW strips the `notification` block (it races the JS SW against
            // the Flutter onMessage listener and the latter sometimes loses).
            $message = CloudMessage::new()
                ->toToken($device->token)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_merge(
                    [
                        'type'  => $type,
                        'title' => $title,
                        'body'  => $body,
                    ],
                    array_map('strval', $data),
                ));

            $this->messaging->send($message);
        } catch (NotFound $e) {
            // Token invalide / UNREGISTERED — on le supprime proprement.
            $device->delete();
            Log::info('[FCM] Removed stale device token', [
                'user_id'  => $device->user_id,
                'platform' => $device->platform,
            ]);
        } catch (Throwable $e) {
            // On logge sans faire crasher le job.
            Log::error('[FCM] Send failed', [
                'user_id' => $device->user_id,
                'token'   => substr($device->token, 0, 20) . '…',
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function isFcmConfigured(): bool
    {
        return $this->messaging !== null
            && filled(config('firebase.projects.app.credentials'));
    }
}
