<?php

declare(strict_types=1);

namespace Tests\Feature\NotificationPreferences;

use App\Enums\NotificationType;
use App\Http\Controllers\UnsubscribeController;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * D24 — Tests du désabonnement 1-click RFC 8058.
 */
class UnsubscribeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // D24-1 : GET avec token valide désactive la préférence et affiche la page
    // -------------------------------------------------------------------------

    public function test_unsubscribe_1click_sets_preference_to_false(): void
    {
        $user = User::factory()->create();

        $url = UnsubscribeController::signedUrl($user, NotificationType::MARKETING, 'email');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Désabonnement confirmé');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'channel' => 'email',
            'type'    => NotificationType::MARKETING,
            'enabled' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // D24-2 : POST 1-click RFC 8058 retourne 200 vide
    // -------------------------------------------------------------------------

    public function test_unsubscribe_post_1click_rfc8058_returns_200(): void
    {
        $user = User::factory()->create();

        $url = UnsubscribeController::signedUrl($user, NotificationType::REVIEW_REQUEST, 'email');

        $response = $this->post($url);

        $response->assertOk();
        $response->assertNoContent(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'channel' => 'email',
            'type'    => NotificationType::REVIEW_REQUEST,
            'enabled' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // D24-3 : Token expiré → 403
    // -------------------------------------------------------------------------

    public function test_unsubscribe_rejects_expired_token(): void
    {
        $user = User::factory()->create();

        // Génère un token déjà expiré (dans le passé)
        $expiredUrl = URL::temporarySignedRoute(
            'unsubscribe',
            now()->subMinute(), // expiré
            [
                'user_id' => $user->id,
                'type'    => NotificationType::MARKETING,
                'channel' => 'email',
            ],
        );

        $response = $this->get($expiredUrl);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // D24-4 : Type inconnu → 422
    // -------------------------------------------------------------------------

    public function test_unsubscribe_rejects_unknown_type(): void
    {
        $user = User::factory()->create();

        // URL signée valide mais type inconnu
        $url = URL::temporarySignedRoute(
            'unsubscribe',
            now()->addDays(30),
            [
                'user_id' => $user->id,
                'type'    => 'totally_fake_type',
                'channel' => 'email',
            ],
        );

        $response = $this->get($url);

        $response->assertStatus(422);
    }
}
