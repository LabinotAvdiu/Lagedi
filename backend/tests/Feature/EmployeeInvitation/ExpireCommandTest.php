<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Jobs\SendInvitationDecisionPush;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ExpireCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_command_marks_past_pending_as_expired(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => CompanyRole::Owner, 'is_active' => true,
        ]);

        $expired = EmployeeInvitation::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'a@x.com', 'specialties' => [],
            'token_hash' => str_repeat('a', 64),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->subHour(),
        ]);
        $stillValid = EmployeeInvitation::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'b@x.com', 'specialties' => [],
            'token_hash' => str_repeat('b', 64),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('invitations:expire')->assertSuccessful();

        $this->assertEquals(InvitationStatus::Expired, $expired->fresh()->status);
        $this->assertEquals(InvitationStatus::Pending, $stillValid->fresh()->status);

        Bus::assertDispatched(SendInvitationDecisionPush::class, 1);
    }
}
