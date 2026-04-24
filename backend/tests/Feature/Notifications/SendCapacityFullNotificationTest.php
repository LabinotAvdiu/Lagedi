<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Jobs\SendCapacityFullNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendCapacityFullNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testCapacityFullJobDispatchedAfterBookingOnCapacitySalon(): void
    {
        Queue::fake();

        $client  = User::factory()->create();
        $company = Company::factory()->create([
            'booking_mode'          => BookingMode::CapacityBased,
            'capacity_auto_approve' => true,
        ]);

        $service = Service::factory()->create([
            'company_id'     => $company->id,
            'duration'       => 30,
            'max_concurrent' => 3,
        ]);

        Sanctum::actingAs($client);

        $date = now()->addDays(5)->format('Y-m-d');

        $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date_time'  => $date . 'T10:00:00',
        ]);

        Queue::assertPushed(
            SendCapacityFullNotification::class,
            fn ($job) => $job->companyId === $company->id
                && $job->date === $date,
        );
    }

    public function testJobDoesNotSendForNonCapacityCompany(): void
    {
        // Vérifie que le job s'arrête si le salon n'est plus en mode capacity.
        Queue::fake();

        $owner   = User::factory()->create();
        $company = Company::factory()->create(['booking_mode' => BookingMode::EmployeeBased]);

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $fcmMock = $this->createMock(\App\Services\FcmService::class);
        $fcmMock->expects($this->never())->method('sendToUser');

        (new SendCapacityFullNotification($company->id, now()->format('Y-m-d')))->handle($fcmMock);
    }
}
