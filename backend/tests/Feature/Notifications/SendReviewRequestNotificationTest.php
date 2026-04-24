<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentConfirmedNotification;
use App\Jobs\SendReviewRequestNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendReviewRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testConfirmedNotificationSchedulesReviewRequest(): void
    {
        Queue::fake();

        $client  = User::factory()->create(['locale' => 'sq']);
        $company = Company::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '11:00:00',
            'end_time'   => '11:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        // Exécute le job synchroniquement (pas via la queue).
        app(\App\Services\FcmService::class); // force la résolution
        $fcmMock = $this->createMock(\App\Services\FcmService::class);
        $fcmMock->expects($this->any())->method('sendToUser');
        $this->app->instance(\App\Services\FcmService::class, $fcmMock);

        (new SendAppointmentConfirmedNotification($appointment))->handle($fcmMock);

        // Vérifie que SendReviewRequestNotification est planifié avec un delay.
        Queue::assertPushed(
            SendReviewRequestNotification::class,
            fn ($job) => $job->appointment->id === $appointment->id,
        );
    }

    public function testReviewRequestJobSkipsCancelledAppointment(): void
    {
        Queue::fake();

        $client  = User::factory()->create(['locale' => 'sq']);
        $company = Company::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        // RDV annulé — le job doit retourner sans envoyer.
        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '12:00:00',
            'end_time'   => '12:30:00',
            'status'     => AppointmentStatus::Cancelled,
            'is_walk_in' => false,
        ]);

        $fcmMock = $this->createMock(\App\Services\FcmService::class);
        // Aucun envoi ne doit avoir lieu.
        $fcmMock->expects($this->never())->method('sendToUser');

        (new SendReviewRequestNotification($appointment))->handle($fcmMock);
    }
}
