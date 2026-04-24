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
            'user_id'    => $owner->id,
            'role'       => CompanyRole::Owner,
            'is_active'  => true,
        ]);

        $token = $token ?? bin2hex(random_bytes(32));
        $invitation = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => 'alice@example.com',
            'first_name'         => 'Alice',
            'last_name'          => 'Martin',
            'specialties'        => [],
            'token_hash'         => hash('sha256', $token),
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->addDays(7),
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
        $this->getJson('/api/invitations/' . str_repeat('z', 64))
            ->assertStatus(404);
    }
}
