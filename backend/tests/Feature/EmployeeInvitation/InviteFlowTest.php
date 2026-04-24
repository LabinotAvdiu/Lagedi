<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Jobs\SendEmployeeInvitationPush;
use App\Mail\EmployeeInvitationLinkMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerWithCompany(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => CompanyRole::Owner,
            'is_active' => true,
        ]);

        return [$owner, $company];
    }

    public function test_owner_can_invite_unknown_email(): void
    {
        Mail::fake();

        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/my-company/employees/invite', [
            'email' => 'alice@example.com',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.firstName', 'Alice')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.hasAccount', false);

        $this->assertDatabaseHas('employee_invitations', [
            'company_id' => $company->id,
            'email' => 'alice@example.com',
            'status' => 'pending',
        ]);

        Mail::assertSent(EmployeeInvitationLinkMail::class);
    }

    public function test_invite_existing_user_sends_push_not_email(): void
    {
        Mail::fake();

        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        User::factory()->create(['email' => 'bob@example.com']);

        $this->postJson('/api/my-company/employees/invite', [
            'email' => 'bob@example.com',
        ])->assertStatus(201)
            ->assertJsonPath('data.hasAccount', true);

        Mail::assertNothingSent();
        // Push job dispatch test in Phase 5 once FCM is wired.
    }

    public function test_reinvite_regenerates_token(): void
    {
        Mail::fake();

        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/my-company/employees/invite', [
            'email' => 'alice@example.com',
        ])->assertStatus(201);

        $firstHash = EmployeeInvitation::where('email', 'alice@example.com')->value('token_hash');

        $this->postJson('/api/my-company/employees/invite', [
            'email' => 'alice@example.com',
        ])->assertStatus(201);

        $this->assertEquals(
            1,
            EmployeeInvitation::where('email', 'alice@example.com')->count(),
            'should have only one pending invitation, not two',
        );

        $secondHash = EmployeeInvitation::where('email', 'alice@example.com')->value('token_hash');
        $this->assertNotEquals($firstHash, $secondHash, 'token must be regenerated');
    }

    public function test_cannot_invite_yourself(): void
    {
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/my-company/employees/invite', [
            'email' => $owner->email,
        ])->assertStatus(422);
    }

    public function test_cannot_invite_existing_member(): void
    {
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $employee = User::factory()->create(['email' => 'emp@example.com']);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => CompanyRole::Employee,
            'is_active' => true,
        ]);

        $this->postJson('/api/my-company/employees/invite', [
            'email' => 'emp@example.com',
        ])->assertStatus(422);
    }

    public function test_resend_regenerates_token_and_resets_expiry(): void
    {
        Mail::fake();
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/my-company/employees/invite', ['email' => 'alice@example.com']);
        $invitation = EmployeeInvitation::where('email', 'alice@example.com')->first();
        $oldHash = $invitation->token_hash;

        $this->travel(3)->days();

        $this->postJson("/api/my-company/employees/invitations/{$invitation->id}/resend")
            ->assertOk();

        $invitation->refresh();
        $this->assertNotEquals($oldHash, $invitation->token_hash);
        $this->assertTrue(now()->diffInDays($invitation->expires_at) >= 6);
    }

    public function test_revoke_marks_invitation_revoked(): void
    {
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/my-company/employees/invite', ['email' => 'alice@example.com']);
        $invitation = EmployeeInvitation::where('email', 'alice@example.com')->first();

        $this->deleteJson("/api/my-company/employees/invitations/{$invitation->id}")
            ->assertOk();

        $this->assertEquals(InvitationStatus::Revoked, $invitation->fresh()->status);
    }

    public function test_list_employees_includes_pending_invitations(): void
    {
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $employee = User::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => CompanyRole::Employee,
            'is_active' => true,
        ]);
        $this->postJson('/api/my-company/employees/invite', ['email' => 'alice@example.com']);

        $response = $this->getJson('/api/my-company/employees');
        $response->assertOk();

        $kinds = collect($response->json('data'))->pluck('kind')->all();
        $this->assertContains('member', $kinds);
        $this->assertContains('invitation', $kinds);
    }

    public function test_list_employees_excludes_refused_by_default(): void
    {
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        EmployeeInvitation::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'r@example.com',
            'specialties' => [],
            'token_hash' => str_repeat('a', 64),
            'status' => InvitationStatus::Refused,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/my-company/employees');
        $emails = collect($response->json('data'))->pluck('email')->filter()->all();
        $this->assertNotContains('r@example.com', $emails);
    }

    public function test_invite_existing_user_dispatches_push_job(): void
    {
        Bus::fake();

        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        User::factory()->create(['email' => 'bob@example.com']);

        $this->postJson('/api/my-company/employees/invite', ['email' => 'bob@example.com']);

        Bus::assertDispatched(SendEmployeeInvitationPush::class);
    }

    public function test_legacy_create_employee_route_returns_404(): void
    {
        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/my-company/employees/create', [
            'email' => 'x@x.com', 'first_name' => 'X', 'last_name' => 'Y',
            'password' => 'P@ssw0rd1234',
        ])->assertStatus(404);
    }
}
