<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
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
            $message = CloudMessage::withTarget('token', $device->token)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_merge(['type' => $type], array_map('strval', $data)));

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
