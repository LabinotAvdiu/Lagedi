<?php

declare(strict_types=1);

namespace Tests\Feature\NotificationPreferences;

use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationLogger;
use Tests\TestCase;

/**
 * Inbox notifications — tests des endpoints enrichis (phase inbox).
 *
 * GET   /api/me/notifications-log          → payload + filtre 30 jours
 * PATCH /api/me/notifications-log/{id}/read  → mark single as read
 * PATCH /api/me/notifications-log/read-all   → mark all as read
 */
class NotificationLogInboxTest extends TestCase
{
    // -------------------------------------------------------------------------
    // index returns logs with payload and within 30 days
    // -------------------------------------------------------------------------

    public function test_index_returns_logs_with_payload_and_within_30_days(): void
    {
        $user = User::factory()->create();

        // Log récent avec payload title/body
        NotificationLogger::log(
            user: $user,
            channel: 'push',
            type: NotificationType::NEW_REVIEW,
            payload: ['title' => 'Nouvel avis', 'body' => 'Un client a laissé 5 étoiles.'],
            refType: 'review',
            refId: 10,
        );

        // Log trop vieux (> 30 jours) — ne doit pas apparaître
        NotificationLog::create([
            'user_id'  => $user->id,
            'channel'  => 'email',
            'type'     => NotificationType::MARKETING,
            'payload'  => ['title' => 'Vieux', 'body' => 'Vieux message'],
            'sent_at'  => now()->subDays(31),
        ]);

        $response = $this->actingAs($user)->getJson('/api/me/notifications-log');

        $response->assertOk();
        $data = $response->json('data');

        // Seul le log récent doit figurer
        $this->assertCount(1, $data);
        $this->assertEquals('push', $data[0]['channel']);
        $this->assertArrayHasKey('payload', $data[0]);
        $this->assertEquals('Nouvel avis', $data[0]['payload']['title']);
    }

    // -------------------------------------------------------------------------
    // mark_as_read sets read_at
    // -------------------------------------------------------------------------

    public function test_mark_as_read_sets_read_at(): void
    {
        $user = User::factory()->create();

        NotificationLogger::log(
            user: $user,
            channel: 'push',
            type: NotificationType::NEW_REVIEW,
            payload: ['title' => 'Avis', 'body' => 'Corps'],
        );

        $log = NotificationLog::where('user_id', $user->id)->first();
        $this->assertNull($log->read_at);

        $response = $this->actingAs($user)
            ->patchJson("/api/me/notifications-log/{$log->id}/read");

        $response->assertOk();
        $log->refresh();
        $this->assertNotNull($log->read_at);
    }

    // -------------------------------------------------------------------------
    // mark_as_read rejects log from other user
    // -------------------------------------------------------------------------

    public function test_mark_as_read_rejects_log_from_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        NotificationLogger::log(
            user: $other,
            channel: 'push',
            type: NotificationType::MARKETING,
            payload: ['title' => 'Test', 'body' => 'Corps'],
        );

        $log = NotificationLog::where('user_id', $other->id)->first();

        $response = $this->actingAs($owner)
            ->patchJson("/api/me/notifications-log/{$log->id}/read");

        $response->assertNotFound();
        $this->assertNull($log->fresh()->read_at);
    }

    // -------------------------------------------------------------------------
    // read_all marks every unread log
    // -------------------------------------------------------------------------

    public function test_read_all_marks_every_unread_log(): void
    {
        $user = User::factory()->create();

        NotificationLogger::log($user, 'push', NotificationType::NEW_REVIEW, ['title' => 'A', 'body' => 'x']);
        NotificationLogger::log($user, 'push', NotificationType::MARKETING,  ['title' => 'B', 'body' => 'y']);
        NotificationLogger::log($user, 'email', NotificationType::REVIEW_REQUEST, ['title' => 'C', 'body' => 'z']);

        $response = $this->actingAs($user)
            ->patchJson('/api/me/notifications-log/read-all');

        $response->assertOk();
        $this->assertEquals(3, $response->json('affected'));

        $unreadCount = NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unreadCount);
    }
}
