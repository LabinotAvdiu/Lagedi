<?php

declare(strict_types=1);

namespace Tests\Feature\NotificationPreferences;

use App\Enums\NotificationType;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\NotificationLogger;
use App\Services\FcmService;
use App\Models\NotificationLog;
use App\Jobs\SendReviewRequestNotification;
use App\Jobs\SendCapacityFullNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Enums\AppointmentStatus;
use Tests\TestCase;

/**
 * D20 — Tests du journal de notifications.
 */
class NotificationLogTest extends TestCase
{
    // -------------------------------------------------------------------------
    // D20-1 : NotificationLogger::log insère bien une ligne
    // -------------------------------------------------------------------------

    public function test_notifications_log_records_successful_push(): void
    {
        $user = User::factory()->create();

        NotificationLogger::log(
            user: $user,
            channel: 'push',
            type: NotificationType::NEW_REVIEW,
            payload: ['reviewId' => 42, 'rating' => 5],
            refType: 'review',
            refId: 42,
        );

        $this->assertDatabaseHas('notifications_log', [
            'user_id'  => $user->id,
            'channel'  => 'push',
            'type'     => NotificationType::NEW_REVIEW,
            'ref_type' => 'review',
            'ref_id'   => 42,
        ]);
    }

    // -------------------------------------------------------------------------
    // D20-2 : NotificationLogger::log fonctionne pour un email
    // -------------------------------------------------------------------------

    public function test_notifications_log_records_email_sent(): void
    {
        $user = User::factory()->create();

        NotificationLogger::log(
            user: $user,
            channel: 'email',
            type: NotificationType::MARKETING,
            payload: ['campaign' => 'diaspora_2026_q2'],
        );

        $this->assertDatabaseHas('notifications_log', [
            'user_id' => $user->id,
            'channel' => 'email',
            'type'    => NotificationType::MARKETING,
        ]);
    }

    // -------------------------------------------------------------------------
    // D20-3 : GET /api/me/notifications-log retourne les logs de l'utilisateur
    // -------------------------------------------------------------------------

    public function test_notifications_log_endpoint_returns_user_logs(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        NotificationLogger::log($user, 'push', NotificationType::REVIEW_REQUEST);
        NotificationLogger::log($other, 'push', NotificationType::MARKETING);

        $response = $this->actingAs($user)
            ->getJson('/api/me/notifications-log');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals(NotificationType::REVIEW_REQUEST, $data[0]['type']);
    }
}
