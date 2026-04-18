<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CompanyRole;
use App\Jobs\SendAppointmentConfirmedNotification;
use App\Jobs\SendAppointmentCreatedNotification;
use App\Jobs\SendAppointmentRejectedNotification;
use App\Jobs\SendAppointmentReminderOwner;
use App\Models\Appointment;
use App\Models\AppointmentNotificationSent;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Models\Service;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test 1 — Owner : GET et PUT des préférences
    // -------------------------------------------------------------------------

    public function testOwnerCanReadAndUpdatePreferences(): void
    {
        $owner   = User::factory()->create();
        $company = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $token = $owner->createToken('test')->plainTextToken;

        // GET — création lazy des préfs par défaut
        $this->withToken($token)
            ->getJson('/api/me/notification-preferences')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'notifyNewBooking'       => true,
                    'notifyQuietDayReminder' => true,
                ],
            ]);

        // PUT — mise à jour
        $this->withToken($token)
            ->putJson('/api/me/notification-preferences', [
                'notifyNewBooking'       => false,
                'notifyQuietDayReminder' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'notifyNewBooking'       => false,
                    'notifyQuietDayReminder' => true,
                ],
            ]);

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id'            => $owner->id,
            'notify_new_booking' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Employee : GET et PUT des préférences
    // -------------------------------------------------------------------------

    public function testEmployeeCanReadAndUpdatePreferences(): void
    {
        $employee = User::factory()->create();
        $company  = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $employee->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Employee->value,
            'is_active'  => true,
        ]);

        $token = $employee->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.notifyNewBooking', true);

        $this->withToken($token)
            ->putJson('/api/me/notification-preferences', [
                'notifyNewBooking'       => true,
                'notifyQuietDayReminder' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.notifyQuietDayReminder', false);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Client (sans rôle pro) : 403 sur les endpoints préférences
    // -------------------------------------------------------------------------

    public function testClientReceives403OnPreferenceEndpoints(): void
    {
        $client = User::factory()->create();
        $token  = $client->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me/notification-preferences')
            ->assertForbidden();

        $this->withToken($token)
            ->putJson('/api/me/notification-preferences', [
                'notifyNewBooking'       => false,
                'notifyQuietDayReminder' => false,
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Test 4 — Enregistrement de device : idempotent, last_seen_at mis à jour
    // -------------------------------------------------------------------------

    public function testDeviceRegisterIsIdempotentAndUpdatesLastSeenAt(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $fcmToken = 'test_fcm_token_' . uniqid();

        // Premier enregistrement
        $this->withToken($token)
            ->postJson('/api/me/devices', [
                'token'    => $fcmToken,
                'platform' => 'android',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('user_devices', [
            'user_id'  => $user->id,
            'token'    => $fcmToken,
            'platform' => 'android',
        ]);

        $firstSeenAt = UserDevice::where('token', $fcmToken)->value('last_seen_at');

        // Attente d'1 seconde pour que last_seen_at change
        sleep(1);

        // Deuxième appel avec le même token — doit être idempotent
        $this->withToken($token)
            ->postJson('/api/me/devices', [
                'token'    => $fcmToken,
                'platform' => 'android',
            ])
            ->assertNoContent();

        $this->assertDatabaseCount('user_devices', 1);

        $updatedSeenAt = UserDevice::where('token', $fcmToken)->value('last_seen_at');

        $this->assertNotEquals($firstSeenAt, $updatedSeenAt, 'last_seen_at should be refreshed on duplicate registration');
    }

    // -------------------------------------------------------------------------
    // Test 5 — Suppression de device : idempotent (no-op si absent)
    // -------------------------------------------------------------------------

    public function testDeviceUnregisterIsIdempotent(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $fcmToken = 'test_fcm_token_delete_' . uniqid();

        // Enregistre puis supprime
        $this->withToken($token)
            ->postJson('/api/me/devices', ['token' => $fcmToken, 'platform' => 'ios'])
            ->assertNoContent();

        $this->withToken($token)
            ->deleteJson('/api/me/devices', ['token' => $fcmToken])
            ->assertNoContent();

        $this->assertDatabaseMissing('user_devices', ['token' => $fcmToken]);

        // Deuxième DELETE — ne doit pas planter (no-op)
        $this->withToken($token)
            ->deleteJson('/api/me/devices', ['token' => $fcmToken])
            ->assertNoContent();
    }

    // -------------------------------------------------------------------------
    // Test 6 — Création d'un appointment dispatch le job
    // -------------------------------------------------------------------------

    public function testAppointmentCreationDispatchesJob(): void
    {
        Queue::fake();

        $client  = User::factory()->create();
        $company = Company::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time'   => '14:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        Queue::assertPushed(
            SendAppointmentCreatedNotification::class,
            fn ($job) => $job->appointment->id === $appointment->id,
        );
    }

    // -------------------------------------------------------------------------
    // Test 7 — updateAppointmentStatus dispatch le bon job
    // -------------------------------------------------------------------------

    public function testStatusChangeDispatchesCorrectJob(): void
    {
        Queue::fake();

        $client  = User::factory()->create();
        $company = Company::factory()->create();
        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time'   => '09:30:00',
            'status'     => AppointmentStatus::Pending,
            'is_walk_in' => false,
        ]);

        Queue::fake(); // reset après la création

        // Confirmed
        $appointment->update(['status' => AppointmentStatus::Confirmed]);

        Queue::assertPushed(
            SendAppointmentConfirmedNotification::class,
            fn ($job) => $job->appointment->id === $appointment->id,
        );

        Queue::fake(); // reset

        // Rejected
        $appointment->update(['status' => AppointmentStatus::Rejected]);

        Queue::assertPushed(
            SendAppointmentRejectedNotification::class,
            fn ($job) => $job->appointment->id === $appointment->id,
        );
    }

    // -------------------------------------------------------------------------
    // Test 8 — SendAppointmentReminderOwner respecte préférences, règle ≤2 et dédup
    // -------------------------------------------------------------------------

    public function testReminderOwnerJobRespectsPreferencesAndRules(): void
    {
        // Queue::fake() empêche l'exécution synchrone des jobs déclenchés par
        // l'observer Appointment lors des factory()->create() — on teste le job
        // ReminderOwner isolément via ->handle() direct.
        Queue::fake();

        $fcmMock = $this->createMock(FcmService::class);
        $this->app->instance(FcmService::class, $fcmMock);

        $owner   = User::factory()->create();
        $company = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $service = Service::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::factory()->create([
            'company_id' => $company->id,
            'service_id' => $service->id,
            'user_id'    => User::factory()->create()->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
        ]);

        // Cas A — préférence désactivée → pas d'envoi
        UserNotificationPreference::updateOrCreate(
            ['user_id' => $owner->id],
            ['notify_new_booking' => true, 'notify_quiet_day_reminder' => false],
        );

        $fcmMock->expects($this->never())->method('sendToUser');

        (new SendAppointmentReminderOwner($appointment, $owner->id))->handle($fcmMock);

        // Cas B — préférence activée mais > 2 appts ce jour-là
        $owner->notificationPreference()->update(['notify_quiet_day_reminder' => true]);

        // Crée un CompanyUser pour l'owner afin que les appts soient comptabilisés
        $cu = CompanyUser::where('user_id', $owner->id)->first();

        // 3 appts pour cet employé ce jour-là
        for ($i = 0; $i < 3; $i++) {
            Appointment::factory()->create([
                'company_id'      => $company->id,
                'company_user_id' => $cu->id,
                'date'            => $appointment->date->format('Y-m-d'),
                'status'          => AppointmentStatus::Confirmed,
            ]);
        }

        $fcmMock2 = $this->createMock(FcmService::class);
        $fcmMock2->expects($this->never())->method('sendToUser');

        (new SendAppointmentReminderOwner($appointment, $owner->id))->handle($fcmMock2);

        // Cas C — dédup : si déjà envoyé → pas d'envoi
        // Remet à 0 appts en créant un nouvel utilisateur propre
        $owner2   = User::factory()->create();
        $company2 = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $owner2->id,
            'company_id' => $company2->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $owner2->id],
            ['notify_new_booking' => true, 'notify_quiet_day_reminder' => true],
        );

        $service2 = Service::factory()->create(['company_id' => $company2->id]);

        $appt2 = Appointment::factory()->create([
            'company_id' => $company2->id,
            'service_id' => $service2->id,
            'user_id'    => User::factory()->create()->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'status'     => AppointmentStatus::Confirmed,
        ]);

        // Pré-marquer comme envoyé
        AppointmentNotificationSent::markSent($appt2->id, $owner2->id, 'appointment.reminder_owner');

        $fcmMock3 = $this->createMock(FcmService::class);
        $fcmMock3->expects($this->never())->method('sendToUser');

        (new SendAppointmentReminderOwner($appt2, $owner2->id))->handle($fcmMock3);

        $this->assertTrue(true); // Atteint seulement si aucune exception
    }
}
