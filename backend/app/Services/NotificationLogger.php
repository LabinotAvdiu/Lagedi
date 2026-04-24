<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * D20 — Helper statique pour enregistrer chaque notification envoyée.
 *
 * Appelé systématiquement dans handle() de chaque job Send*,
 * après l'envoi FCM réussi ou après Mail::send().
 *
 * L'insertion est non-bloquante : une exception de DB est loggée
 * mais ne fait pas crasher le job parent.
 */
class NotificationLogger
{
    /**
     * @param User        $user    Destinataire
     * @param string      $channel push|email|in-app
     * @param string      $type    NotificationType::* ou type transactionnel
     * @param array       $payload Données libres (titre, body, data FCM…)
     * @param string|null $refType Type de resource liée (appointment, review…)
     * @param int|null    $refId   ID de la resource liée
     */
    public static function log(
        User $user,
        string $channel,
        string $type,
        array $payload = [],
        ?string $refType = null,
        ?int $refId = null,
    ): void {
        try {
            NotificationLog::create([
                'user_id'  => $user->id,
                'channel'  => $channel,
                'type'     => $type,
                'payload'  => $payload ?: null,
                'sent_at'  => now(),
                'ref_type' => $refType,
                'ref_id'   => $refId,
            ]);
        } catch (\Throwable $e) {
            // Non-bloquant : si la table est inaccessible, on logge seulement.
            Log::error('[NotificationLogger] Failed to log notification', [
                'user_id' => $user->id,
                'channel' => $channel,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
