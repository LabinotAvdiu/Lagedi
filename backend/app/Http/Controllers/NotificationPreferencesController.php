<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * D19 — Gestion des préférences de notifications granulaires.
 *
 * GET  /api/me/notification-preferences  → retourne le tableau (channel × type)
 * PATCH /api/me/notification-preferences → bulk update
 *
 * Les types transactionnels (confirmations, annulations, OTP…) ne sont pas
 * dans cette table — ils sont toujours envoyés et ne peuvent pas être désactivés.
 */
class NotificationPreferencesController extends Controller
{
    /**
     * GET /api/me/notification-preferences
     *
     * Retourne toutes les préférences de l'utilisateur,
     * en lazy-initialisant les lignes manquantes avec enabled=true.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // S'assure que toutes les lignes (channel × type) existent
        $user->seedDefaultNotificationPreferences();

        $prefs = NotificationPreference::where('user_id', $user->id)
            ->orderBy('channel')
            ->orderBy('type')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $prefs->map(fn (NotificationPreference $p) => [
                'channel' => $p->channel,
                'type'    => $p->type,
                'enabled' => $p->enabled,
            ])->values(),
        ]);
    }

    /**
     * PATCH /api/me/notification-preferences
     *
     * Bulk update — body : array de { channel, type, enabled }.
     * Seuls les types configurables (NotificationType::all()) sont acceptés.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user  = $request->user();
        $items = $request->validated();

        foreach ($items as $item) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'channel' => $item['channel'],
                    'type'    => $item['type'],
                ],
                ['enabled' => $item['enabled']],
            );
        }

        // Recharge l'état complet après mise à jour
        $prefs = NotificationPreference::where('user_id', $user->id)
            ->orderBy('channel')
            ->orderBy('type')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated.',
            'data'    => $prefs->map(fn (NotificationPreference $p) => [
                'channel' => $p->channel,
                'type'    => $p->type,
                'enabled' => $p->enabled,
            ])->values(),
        ]);
    }
}
