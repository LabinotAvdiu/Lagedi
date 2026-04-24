<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\CompanyRole;
use App\Jobs\SendNewReviewNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendNewReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testReviewStoreDispatchesSendNewReviewNotification(): void
    {
        Queue::fake();

        $owner   = User::factory()->create(['locale' => 'sq']);
        $company = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $client  = User::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        // RDV passé et eligible pour review
        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->subDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/appointments/{$appointment->id}/review", [
            'rating'  => 5,
            'comment' => 'Super salon, très professionnel.',
        ])->assertStatus(201);

        Queue::assertPushed(
            SendNewReviewNotification::class,
            fn ($job) => $job->review->company_id === $company->id
                && $job->review->rating === 5,
        );
    }

    public function testJobIsNotDispatchedForLowRatingDifferentKey(): void
    {
        // Vérifie que le job est bien dispatché même pour une note 2 étoiles
        // (le choix du titre est une logique interne au job, pas au trigger)
        Queue::fake();

        $owner   = User::factory()->create(['locale' => 'sq']);
        $company = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $client  = User::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->subDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/appointments/{$appointment->id}/review", [
            'rating'  => 2,
            'comment' => 'Pas terrible.',
        ])->assertStatus(201);

        Queue::assertPushed(
            SendNewReviewNotification::class,
            fn ($job) => $job->review->rating === 2,
        );
    }
}
