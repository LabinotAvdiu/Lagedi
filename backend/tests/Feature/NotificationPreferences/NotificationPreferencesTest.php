<?php

declare(strict_types=1);

namespace Tests\Feature\NotificationPreferences;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Tests\TestCase;

/**
 * D19 — Tests des préférences de notifications granulaires.
 */
class NotificationPreferencesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // D19-1 : GET retourne les valeurs par défaut pour un nouvel utilisateur
    // -------------------------------------------------------------------------

    public function test_get_preferences_returns_defaults_for_new_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/me/notification-preferences/granular');

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);

        $data = $response->json('data');

        // Doit contenir channels × types lignes
        $channels = ['push', 'email', 'in-app'];
        $types    = NotificationType::all();

        $this->assertCount(count($channels) * count($types), $data);

        // Toutes activées par défaut
        foreach ($data as $pref) {
            $this->assertTrue($pref['enabled'], "Expected enabled=true for {$pref['channel']}:{$pref['type']}");
        }
    }

    // -------------------------------------------------------------------------
    // D19-2 : PATCH met à jour une préférence
    // -------------------------------------------------------------------------

    public function test_patch_updates_preferences(): void
    {
        $user = User::factory()->create();

        $payload = [
            ['channel' => 'push', 'type' => NotificationType::MARKETING, 'enabled' => false],
            ['channel' => 'email', 'type' => NotificationType::REVIEW_REQUEST, 'enabled' => false],
        ];

        $response = $this->actingAs($user)
            ->patchJson('/api/me/notification-preferences/granular', $payload);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);

        // Vérifie en base
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'channel' => 'push',
            'type'    => NotificationType::MARKETING,
            'enabled' => 0,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'channel' => 'email',
            'type'    => NotificationType::REVIEW_REQUEST,
            'enabled' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // D19-3 : PATCH rejette un type inconnu
    // -------------------------------------------------------------------------

    public function test_patch_rejects_invalid_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/me/notification-preferences/granular', [
                ['channel' => 'push', 'type' => 'totally_fake_type', 'enabled' => false],
            ]);

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // D19-3b : PATCH rejette un canal inconnu
    // -------------------------------------------------------------------------

    public function test_patch_rejects_invalid_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/me/notification-preferences/granular', [
                ['channel' => 'smoke_signal', 'type' => NotificationType::MARKETING, 'enabled' => false],
            ]);

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // D19-4 : isNotificationEnabled retourne true pour un type transactionnel
    // -------------------------------------------------------------------------

    public function test_is_notification_enabled_returns_true_for_transactional(): void
    {
        $user = User::factory()->create();

        // Même si aucune ligne n'existe, les transactionnels sont toujours true
        foreach (NotificationType::transactional() as $transactionalType) {
            $this->assertTrue(
                $user->isNotificationEnabled('push', $transactionalType),
                "Expected true for transactional type: {$transactionalType}"
            );
            $this->assertTrue(
                $user->isNotificationEnabled('email', $transactionalType),
            );
        }
    }

    // -------------------------------------------------------------------------
    // D19-5 : isNotificationEnabled respecte la préférence en base
    // -------------------------------------------------------------------------

    public function test_is_notification_enabled_respects_preference(): void
    {
        $user = User::factory()->create();

        // Désactive la préférence (l'observer a déjà seedé enabled=true, on la met à jour)
        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'channel' => 'push', 'type' => NotificationType::MARKETING],
            ['enabled' => false],
        );

        $this->assertFalse(
            $user->isNotificationEnabled('push', NotificationType::MARKETING),
            'Expected false — user opted out of marketing push'
        );

        // D'autres canaux non configurés restent true par défaut
        $this->assertTrue(
            $user->isNotificationEnabled('email', NotificationType::MARKETING),
            'Expected true — no preference set for email:marketing, defaults to true'
        );
    }
}
