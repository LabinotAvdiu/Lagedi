<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterWithTokenTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingInvitation(?string $token = null): array
    {
        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create(['name' => 'Salon X']);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => CompanyRole::Owner,
            'is_active' => true,
        ]);

        $token = $token ?? bin2hex(random_bytes(32));
        $invitation = EmployeeInvitation::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'alice@example.com',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'specialties' => [],
            'token_hash' => hash('sha256', $token),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);

        return [$owner, $company, $invitation, $token];
    }

    public function test_public_lookup_returns_invitation_details(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.companyName', 'Salon X')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.firstName', 'Alice');
    }

    public function test_public_lookup_returns_410_for_expired(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/invitations/{$token}")->assertStatus(410);
    }

    public function test_public_lookup_returns_404_for_unknown(): void
    {
        $this->getJson('/api/invitations/'.str_repeat('z', 64))
            ->assertStatus(404);
    }

    public function test_register_with_valid_token_creates_user_and_pivot(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

        $payload = [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@example.com',
            'password' => 'P@ssw0rd1234',
            'phone' => '+38344000000',
            'invitation_token' => $token,
        ];

        $response = $this->postJson('/api/auth/register', $payload);
        $response->assertStatus(201);

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at, 'email must be auto-verified');

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $invitation->refresh();
        $this->assertEquals(InvitationStatus::Accepted, $invitation->status);
        $this->assertEquals($user->id, $invitation->resulting_user_id);
    }

    public function test_register_with_token_but_mismatched_email_fails_422(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

        $this->postJson('/api/auth/register', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'someone-else@example.com',
            'password' => 'P@ssw0rd1234',
            'phone' => '+1',
            'invitation_token' => $token,
        ])->assertStatus(422);
    }

    public function test_register_with_expired_token_fails_410(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->postJson('/api/auth/register', [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@example.com',
            'password' => 'P@ssw0rd1234',
            'phone' => '+1',
            'invitation_token' => $token,
        ])->assertStatus(410);
    }

    public function test_normal_register_with_pending_invite_email_succeeds(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@example.com',
            'password' => 'P@ssw0rd1234',
            'phone' => '+1',
        ]);
        $response->assertStatus(201);

        $alice = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($alice, 'alice should have been created');

        // Invitation stays pending (no auto-acceptance without the token).
        $this->assertEquals(InvitationStatus::Pending, $invitation->fresh()->status);

        // No pivot was created for Alice (the invitation hasn't been accepted yet).
        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id' => $alice->id,
        ]);
    }
}
