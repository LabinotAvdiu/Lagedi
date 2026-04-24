<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentCancelledByClientNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Client can delete their account
    // -------------------------------------------------------------------------

    public function testClientCanDeleteAccount(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        // Row is soft-deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // PII anonymised
        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => 'Utilisateur',
            'last_name'  => 'supprimé',
            'email'      => "deleted-{$user->id}@termini-im.com",
            'phone'      => null,
        ]);

        // Sanctum tokens revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // -------------------------------------------------------------------------
    // 2. Client with future appointment — appointments cancelled + job queued
    // -------------------------------------------------------------------------

    public function testClientFutureAppointmentsAreCancelledOnAccountDeletion(): void
    {
        Queue::fake();

        $user    = User::factory()->create();
        $company = Company::factory()->create();
        $service = \App\Models\Service::factory()->create(['company_id' => $company->id]);

        $future = Appointment::factory()->create([
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->addDay()->toDateString(),
            'status'     => AppointmentStatus::Confirmed,
        ]);

        // Past appointment — should NOT be cancelled
        $past = Appointment::factory()->create([
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => now()->subDay()->toDateString(),
            'status'     => AppointmentStatus::Confirmed,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        // Future appointment cancelled with reason
        $this->assertDatabaseHas('appointments', [
            'id'                  => $future->id,
            'status'              => AppointmentStatus::Cancelled->value,
            'cancellation_reason' => 'account_deleted',
        ]);

        // Past appointment untouched
        $this->assertDatabaseHas('appointments', [
            'id'     => $past->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        // Notification dispatched once (for future appointment only)
        Queue::assertPushed(SendAppointmentCancelledByClientNotification::class, 1);
    }

    // -------------------------------------------------------------------------
    // 3. Owner with active salon — blocked with 422
    // -------------------------------------------------------------------------

    public function testOwnerWithActiveSalonCannotDeleteAccount(): void
    {
        $user    = User::factory()->create(['role' => 'company']);
        $company = Company::factory()->create();

        CompanyUser::create([
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertStatus(422)
            ->assertJsonPath('code', 'owner_has_active_salon');

        // User must still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNull(User::withTrashed()->find($user->id)?->deleted_at);
    }

    // -------------------------------------------------------------------------
    // 4. Unauthenticated request — 401
    // -------------------------------------------------------------------------

    public function testUnauthenticatedCannotDeleteAccount(): void
    {
        $this->deleteJson('/api/auth/account')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // 5. Device tokens removed on deletion
    // -------------------------------------------------------------------------

    public function testDeviceTokensDeletedOnAccountDeletion(): void
    {
        $user = User::factory()->create();
        UserDevice::create([
            'user_id'  => $user->id,
            'token'    => 'fcm-token-abc',
            'platform' => 'android',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        $this->assertDatabaseCount('user_devices', 0);
    }
}
