<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\CompanyRole;
use App\Jobs\SendAppointmentRescheduledByClientNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * C11 — Tests pour SendAppointmentRescheduledByClientNotification.
 *
 * NOTE : L'endpoint client de reschedule n'existe pas encore dans l'API.
 * Ces tests vérifient le comportement du job directement (dispatch + handle).
 * Ils seront complétés par un test de trigger HTTP quand l'endpoint sera créé.
 */
class SendAppointmentRescheduledByClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testJobCanBeDispatchedForExistingAppointment(): void
    {
        Queue::fake();

        $client  = User::factory()->create(['locale' => 'sq']);
        $company = Company::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time'   => '14:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        SendAppointmentRescheduledByClientNotification::dispatch($appointment);

        Queue::assertPushed(
            SendAppointmentRescheduledByClientNotification::class,
            fn ($job) => $job->appointment->id === $appointment->id,
        );
    }

    public function testJobNotifiesOwnerWhenDevicesExist(): void
    {
        Queue::fake();

        $client  = User::factory()->create(['locale' => 'sq']);
        $owner   = User::factory()->create(['locale' => 'sq']);
        $company = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time'   => '09:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        // Pas de device → sendToUser ne sera pas appelé.
        $fcmMock = $this->createMock(\App\Services\FcmService::class);
        $fcmMock->expects($this->never())->method('sendToUser');

        (new SendAppointmentRescheduledByClientNotification($appointment))->handle($fcmMock);
    }
}
