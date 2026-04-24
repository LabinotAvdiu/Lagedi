<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\SendEmployeeInvitationEmail;
use App\Mail\WelcomeEmployeeInvitationMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeEmployeeInvitationMailTest extends TestCase
{
    private User    $owner;
    private User    $employee;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role'   => 'company',
            'locale' => 'fr',
        ]);

        $this->employee = User::factory()->create([
            'role'   => 'user',
            'locale' => 'sq',
        ]);

        $this->company = Company::create([
            'name'    => 'Salon Gjilan',
            'address' => 'Rruga Bulevardi',
            'city'    => 'Gjilan',
            'email'   => $this->owner->email,
            'gender'  => 'both',
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id'    => $this->owner->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);
    }

    public function testJobIsQueuedImmediately(): void
    {
        Queue::fake();

        SendEmployeeInvitationEmail::dispatch($this->employee, $this->owner, $this->company);

        Queue::assertPushed(SendEmployeeInvitationEmail::class);
    }

    public function testMailIsSentToEmployee(): void
    {
        Mail::fake();

        (new SendEmployeeInvitationEmail($this->employee, $this->owner, $this->company))->handle();

        Mail::assertSent(WelcomeEmployeeInvitationMail::class, function ($mail) {
            return $mail->hasTo($this->employee->email);
        });
    }

    public function testMailSubjectContainsOwnerName(): void
    {
        Mail::fake();

        (new SendEmployeeInvitationEmail($this->employee, $this->owner, $this->company))->handle();

        Mail::assertSent(WelcomeEmployeeInvitationMail::class, function ($mail) {
            return str_contains($mail->envelope()->subject, $this->owner->first_name);
        });
    }

    public function testMailBodyContainsInviteUrl(): void
    {
        $mail = new WelcomeEmployeeInvitationMail(
            employee: $this->employee,
            owner:    $this->owner,
            company:  $this->company,
        );

        $content = $mail->content();

        $this->assertStringContainsString(
            (string) $this->company->id,
            $content->with['inviteUrl'],
        );
    }

    public function testMailUsesSalonAsFrom(): void
    {
        $mail = new WelcomeEmployeeInvitationMail(
            employee: $this->employee,
            owner:    $this->owner,
            company:  $this->company,
        );

        $envelope = $mail->envelope();

        $this->assertSame($this->company->name, $envelope->from->name);
    }
}
