<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des préférences de notifications push (owner/employé uniquement).
 *
 * Règles métier :
 * - Seuls les utilisateurs ayant un rôle owner ou employee dans au moins
 *   un company peuvent accéder à ces endpoints (les clients reçoivent 403).
 * - Les notifications "configurables" sont :
 *     • notify_new_booking       — nouveau rendez-vous créé dans le salon
 *     • notify_quiet_day_reminder — rappel 1h avant si ≤ 2 appts dans la journée
 * - Les rappels client (confirmed, rejected, reminder_evening, reminder_2h)
 *   sont forcés et non configurables.
 * - La row est créée avec les valeurs par défaut (true/true) au premier GET
 *   via firstOrCreate (lazy initialization).
 */
class NotificationPreferenceController extends Controller
{
    /**
     * GET /api/me/notification-preferences
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->hasProRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Only owners and employees can manage notification preferences.',
            ], 403);
        }

        $prefs = $user->ensureNotificationPreference();

        return response()->json([
            'success' => true,
            'data'    => $this->format($prefs),
        ]);
    }

    /**
     * PUT /api/me/notification-preferences
     */
    public function update(Request $request): JsonResponse
    {
        if (! $this->hasProRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Only owners and employees can manage notification preferences.',
            ], 403);
        }

        $validated = $request->validate([
            'notifyNewBooking'       => ['required', 'boolean'],
            'notifyQuietDayReminder' => ['required', 'boolean'],
        ]);

        $prefs = $request->user()->ensureNotificationPreference();

        $prefs->update([
            'notify_new_booking'        => $validated['notifyNewBooking'],
            'notify_quiet_day_reminder' => $validated['notifyQuietDayReminder'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->format($prefs->fresh()),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Vérifie si l'utilisateur est owner ou employee dans au moins un company.
     */
    private function hasProRole(Request $request): bool
    {
        return $request->user()
            ->companyUsers()
            ->whereIn('role', [CompanyRole::Owner->value, CompanyRole::Employee->value])
            ->exists();
    }

    private function format(UserNotificationPreference $prefs): array
    {
        return [
            'notifyNewBooking'       => $prefs->notify_new_booking,
            'notifyQuietDayReminder' => $prefs->notify_quiet_day_reminder,
        ];
    }
}
