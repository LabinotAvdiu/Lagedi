<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * D21 / D22 / D23 — Gates de contrôle des notifications non-transactionnelles.
 *
 * Les trois gates sont indépendants et s'appliquent en cascade dans les jobs :
 *   1. isNotificationEnabled()  → préférence opt-out (D19)
 *   2. respectsQuietHours()     → plage horaire 9h-21h (D21)
 *   3. isDuplicate()            → dedup 10min Redis (D22)
 *   4. exceedsFrequencyCap()    → max 5 non-tx / semaine (D23)
 *
 * Tous retournent true = "envoi autorisé" pour les transactionnels,
 * respectant ainsi la règle "transactional est toujours envoyé".
 */
class NotificationGate
{
    // -------------------------------------------------------------------------
    // D21 — Quiet hours 21h–9h heure locale du destinataire
    // -------------------------------------------------------------------------

    /**
     * Retourne true si la notification peut être envoyée maintenant.
     *
     * Pour les transactionnels → toujours true.
     * Pour les non-transactionnels → true si heure locale ∈ [9h, 21h[.
     */
    public static function respectsQuietHours(User $user, string $type): bool
    {
        if (NotificationType::isTransactional($type)) {
            return true;
        }

        $tz       = $user->timezone ?? 'Europe/Tirane';
        $localHour = (int) Carbon::now($tz)->format('G');

        // Plage autorisée : 9h inclus jusqu'à 21h exclus
        return $localHour >= 9 && $localHour < 21;
    }

    /**
     * Calcule le prochain 9h local du destinataire pour un re-dispatch différé.
     */
    public static function nextAllowedAt(User $user): Carbon
    {
        $tz    = $user->timezone ?? 'Europe/Tirane';
        $local = Carbon::now($tz);

        // Si on est avant 9h aujourd'hui, on cible 9h today ; sinon 9h tomorrow
        if ($local->hour < 9) {
            $next = $local->copy()->setHour(9)->setMinute(0)->setSecond(0);
        } else {
            $next = $local->copy()->addDay()->setHour(9)->setMinute(0)->setSecond(0);
        }

        return $next->utc();
    }

    // -------------------------------------------------------------------------
    // D22 — Dedup 10 minutes via Redis
    // -------------------------------------------------------------------------

    /**
     * Retourne true si la notification est un doublon (déjà envoyée dans les 10 min).
     *
     * Clé Redis : notif:dedup:{user_id}:{type}:{refKey|none}
     * TTL : 600 secondes.
     *
     * Pour les transactionnels → toujours false (jamais dédupliqué ici,
     * ils ont leur propre dedup via AppointmentNotificationSent).
     *
     * @param string|null $refKey Identifiant de la resource (ex: "appt_42", "review_7").
     *                            Null si la notif n'est pas liée à une resource précise.
     */
    public static function isDuplicate(User $user, string $type, ?string $refKey = null): bool
    {
        if (NotificationType::isTransactional($type)) {
            return false;
        }

        $key = self::dedupKey($user->id, $type, $refKey);

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, 1, 600); // TTL 10 min

        return false;
    }

    // -------------------------------------------------------------------------
    // D23 — Frequency cap 5 non-transactionnelles / 7 jours
    // -------------------------------------------------------------------------

    /**
     * Retourne true si l'utilisateur a déjà reçu ≥ 5 non-transactionnelles
     * dans les 7 derniers jours.
     *
     * Pour les transactionnels → toujours false.
     */
    public static function exceedsFrequencyCap(User $user): bool
    {
        if (empty(NotificationType::gated())) {
            return false;
        }

        $count = NotificationLog::where('user_id', $user->id)
            ->where('sent_at', '>=', now()->subWeek())
            ->whereIn('type', NotificationType::gated())
            ->count();

        return $count >= 5;
    }

    // -------------------------------------------------------------------------
    // D19 — Preference opt-out check
    // -------------------------------------------------------------------------

    /**
     * Retourne true si la notification est activée pour ce canal × type.
     *
     * Pour les transactionnels → toujours true.
     * Pour les configurables → vérifie notification_preferences.
     *
     * Si aucune ligne n'existe (user jamais seeded), on considère enabled = true.
     */
    public static function isPreferenceEnabled(User $user, string $channel, string $type): bool
    {
        if (NotificationType::isTransactional($type)) {
            return true;
        }

        $pref = \App\Models\NotificationPreference::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('type', $type)
            ->first();

        // Pas de ligne = jamais configuré = activé par défaut
        return $pref === null || $pref->enabled;
    }

    // -------------------------------------------------------------------------
    // Helpers internes
    // -------------------------------------------------------------------------

    private static function dedupKey(int $userId, string $type, ?string $refKey): string
    {
        return sprintf('notif:dedup:%d:%s:%s', $userId, $type, $refKey ?? 'none');
    }
}
