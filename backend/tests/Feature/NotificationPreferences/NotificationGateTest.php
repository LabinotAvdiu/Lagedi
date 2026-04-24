<?php

declare(strict_types=1);

namespace Tests\Feature\NotificationPreferences;

use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationGate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * D21 + D22 + D23 — Tests des gates de notifications.
 */
class NotificationGateTest extends TestCase
{
    // -------------------------------------------------------------------------
    // D21 — Quiet hours
    // -------------------------------------------------------------------------

    public function test_quiet_hours_defers_marketing_job_until_morning(): void
    {
        // Utilisateur avec fuseau Europe/Tirane
        $user = User::factory()->create(['timezone' => 'Europe/Tirane']);

        // Simule 22h locale (hors plage 9h-21h)
        Carbon::setTestNow(Carbon::now('Europe/Tirane')->setHour(22)->setMinute(0)->setSecond(0)->utc());

        $result = NotificationGate::respectsQuietHours($user, NotificationType::MARKETING);
        $this->assertFalse($result, 'Expected false at 22h local — quiet hours active');

        Carbon::setTestNow(); // Reset
    }

    public function test_quiet_hours_allows_send_during_day(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Tirane']);

        // Simule 14h locale (dans la plage 9h-21h)
        Carbon::setTestNow(Carbon::now('Europe/Tirane')->setHour(14)->setMinute(0)->setSecond(0)->utc());

        $result = NotificationGate::respectsQuietHours($user, NotificationType::MARKETING);
        $this->assertTrue($result, 'Expected true at 14h local — within allowed window');

        Carbon::setTestNow();
    }

    public function test_transactional_job_ignores_quiet_hours(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Tirane']);

        // Simule 23h locale
        Carbon::setTestNow(Carbon::now('Europe/Tirane')->setHour(23)->setMinute(0)->setSecond(0)->utc());

        // Les types transactionnels ignorent les quiet hours
        foreach (NotificationType::transactional() as $type) {
            $this->assertTrue(
                NotificationGate::respectsQuietHours($user, $type),
                "Expected true for transactional type {$type} even at 23h"
            );
        }

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------------------
    // D22 — Dedup 10 min
    // -------------------------------------------------------------------------

    public function test_dedup_blocks_second_identical_notif_within_10min(): void
    {
        $user = User::factory()->create();

        // Premier appel : pas de doublon
        $first = NotificationGate::isDuplicate($user, NotificationType::NEW_REVIEW, 'review_99');
        $this->assertFalse($first, 'First call should not be a duplicate');

        // Second appel dans les 10 min : doublon détecté
        $second = NotificationGate::isDuplicate($user, NotificationType::NEW_REVIEW, 'review_99');
        $this->assertTrue($second, 'Second call within TTL should be a duplicate');
    }

    public function test_dedup_allows_different_ref_keys(): void
    {
        $user = User::factory()->create();

        NotificationGate::isDuplicate($user, NotificationType::NEW_REVIEW, 'review_1');
        $differentRef = NotificationGate::isDuplicate($user, NotificationType::NEW_REVIEW, 'review_2');

        $this->assertFalse($differentRef, 'Different ref_key should not be blocked');
    }

    // -------------------------------------------------------------------------
    // D23 — Frequency cap
    // -------------------------------------------------------------------------

    public function test_frequency_cap_blocks_after_5_non_transactional_per_week(): void
    {
        $user = User::factory()->create();

        // Insère 5 lignes non-transactionnelles dans la semaine
        for ($i = 0; $i < 5; $i++) {
            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'push',
                'type'    => NotificationType::MARKETING,
                'sent_at' => now()->subDays(1),
            ]);
        }

        $this->assertTrue(
            NotificationGate::exceedsFrequencyCap($user),
            'Expected cap exceeded after 5 non-transactional notifications'
        );
    }

    public function test_frequency_cap_allows_below_limit(): void
    {
        $user = User::factory()->create();

        // Seulement 4 — sous le seuil
        for ($i = 0; $i < 4; $i++) {
            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'push',
                'type'    => NotificationType::MARKETING,
                'sent_at' => now()->subDays(1),
            ]);
        }

        $this->assertFalse(
            NotificationGate::exceedsFrequencyCap($user),
            'Expected cap NOT exceeded with only 4 non-transactional notifications'
        );
    }

    public function test_transactional_notifs_dont_count_in_cap(): void
    {
        $user = User::factory()->create();

        // 10 notifications transactionnelles
        for ($i = 0; $i < 10; $i++) {
            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'push',
                'type'    => 'appointment.confirmed', // transactionnel
                'sent_at' => now()->subHours(1),
            ]);
        }

        // Le cap ne compte que les non-transactionnels (gated types)
        $this->assertFalse(
            NotificationGate::exceedsFrequencyCap($user),
            'Transactional notifications must not count toward frequency cap'
        );
    }
}
