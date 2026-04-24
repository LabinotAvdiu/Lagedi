<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Jobs\SendAppointmentRescheduledByOwnerNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendAppointmentRescheduledByOwnerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testJobCanBeDispatchedWithOldDatetime(): void
    {
        Queue::fake();

        $client  = User::factory()->create();
        $company = Company::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        SendAppointmentRescheduledByOwnerNotification::dispatch(
            $appointment,
            now()->addDays(3)->format('Y-m-d'),
            '09:00:00',
        );

        Queue::assertPushed(
            SendAppointmentRescheduledByOwnerNotification::class,
            fn ($job) => $job->appointment->id === $appointment->id
                && $job->oldTime === '09:00:00',
        );
    }
}
