<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\SendWelcomeOwnerEmail;
use App\Mail\WelcomeOwnerMail;
use Illuminate\Support\Facades\App;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeOwnerMailTest extends TestCase
{
    private User    $owner;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role'              => 'company',
            'locale'            => 'fr',
            'email_verified_at' => now(),
        ]);

        $this->company = Company::create([
            'name'    => 'Salon Test',
            'address' => '12 rue de la Paix',
            'city'    => 'Prishtinë',
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

    public function testJobIsQueuedWithCorrectOwner(): void
    {
        Queue::fake();

        SendWelcomeOwnerEmail::dispatch($this->owner);

        Queue::assertPushed(SendWelcomeOwnerEmail::class, function ($job) {
            return $job->user->id === $this->owner->id;
        });
    }

    public function testMailIsQueuedToOwnerAddress(): void
    {
        Mail::fake();

        (new SendWelcomeOwnerEmail($this->owner))->handle();

        Mail::assertSent(WelcomeOwnerMail::class, function ($mail) {
            return $mail->hasTo($this->owner->email);
        });
    }

    public function testMailSubjectIsLocalizedFr(): void
    {
        // Test the envelope subject directly with app locale forced to fr.
        app()->setLocale('fr');
        $mail    = new WelcomeOwnerMail($this->owner, $this->company);
        $subject = $mail->envelope()->subject;

        $this->assertStringContainsString('5 étapes', $subject);
    }

    public function testMailNotSentForUnverifiedOwner(): void
    {
        Mail::fake();

        $this->owner->update(['email_verified_at' => null]);

        (new SendWelcomeOwnerEmail($this->owner))->handle();

        Mail::assertNothingSent();
    }

    public function testMailNotSentWhenNoCompany(): void
    {
        Mail::fake();

        $ownerWithoutCompany = User::factory()->create([
            'role'              => 'company',
            'locale'            => 'fr',
            'email_verified_at' => now(),
        ]);

        (new SendWelcomeOwnerEmail($ownerWithoutCompany))->handle();

        Mail::assertNothingSent();
    }
}
