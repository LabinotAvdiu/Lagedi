<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * D24 — Désabonnement 1-click conforme RFC 8058.
 *
 * GET  /unsubscribe?token={signed}  → page de confirmation blade
 * POST /unsubscribe?token={signed}  → 1-click RFC 8058 (corps vide, même effet)
 *
 * Token : URL signée Laravel (valide 30 jours) avec params user_id + type.
 * La route est web (pas api) — pas d'authentification Sanctum requise.
 */
class UnsubscribeController extends Controller
{
    public function handle(Request $request): Response|\Illuminate\View\View
    {
        // Valide la signature de l'URL (Laravel URL::signedRoute)
        if (! $request->hasValidSignature()) {
            abort(403, 'Lien de désabonnement invalide ou expiré.');
        }

        $userId = (int) $request->query('user_id');
        $type   = (string) $request->query('type');
        $channel = (string) ($request->query('channel', 'email'));

        // Vérifie que le type est un type configurable valide
        if (! NotificationType::isValid($type)) {
            abort(422, 'Type de notification inconnu.');
        }

        $user = User::find($userId);

        if (! $user) {
            abort(404, 'Utilisateur introuvable.');
        }

        // Désactive la préférence pour ce canal × type
        NotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'channel' => $channel,
                'type'    => $type,
            ],
            ['enabled' => false],
        );

        // POST 1-click RFC 8058 → 200 vide
        if ($request->isMethod('POST')) {
            return response('', 200);
        }

        // GET → page de confirmation
        return view('emails.unsubscribe_confirm', [
            'type'  => $type,
            'email' => $user->email,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper statique — génère l'URL signée pour l'email
    // -------------------------------------------------------------------------

    /**
     * Génère une URL signée de désabonnement valide 30 jours.
     *
     * @param  User   $user
     * @param  string $type     NotificationType::*
     * @param  string $channel  push|email|in-app (défaut email)
     */
    public static function signedUrl(User $user, string $type, string $channel = 'email'): string
    {
        return URL::temporarySignedRoute(
            'unsubscribe',
            now()->addDays(30),
            [
                'user_id' => $user->id,
                'type'    => $type,
                'channel' => $channel,
            ],
        );
    }
}
