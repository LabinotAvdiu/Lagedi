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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcceptRefuseTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingFor(string $email): array
    {
        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => CompanyRole::Owner,
            'is_active'  => true,
        ]);
        $invitation = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => $email,
            'specialties'        => [],
            'token_hash'         => str_repeat('a', 64),
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->addDays(7),
        ]);
        return [$owner, $company, $invitation];
    }

    public function test_user_sees_their_pending_invitations(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, $company, $invitation] = $this->makePendingFor('me@example.com');
        $this->makePendingFor('someone-else@example.com');

        $response = $this->getJson('/api/me/invitations');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $invitation->id);
    }

    public function test_user_can_accept_invitation(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, $company, $invitation] = $this->makePendingFor('me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'is_active'  => true,
        ]);

        $this->assertEquals(InvitationStatus::Accepted, $invitation->fresh()->status);
    }

    public function test_user_can_refuse_invitation(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, , $invitation] = $this->makePendingFor('me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/refuse")
            ->assertOk();

        $this->assertEquals(InvitationStatus::Refused, $invitation->fresh()->status);
    }

    public function test_cannot_act_on_someone_elses_invitation(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, , $invitation] = $this->makePendingFor('not-me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/accept")
            ->assertStatus(404);
    }

    public function test_double_accept_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, $company, $invitation] = $this->makePendingFor('me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/accept")->assertOk();
        $this->postJson("/api/me/invitations/{$invitation->id}/accept")->assertOk();

        $this->assertEquals(
            1,
            CompanyUser::where('company_id', $company->id)->where('user_id', $user->id)->count(),
        );
    }
}
