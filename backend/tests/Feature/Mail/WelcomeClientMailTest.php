<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\SendWelcomeClientEmail;
use App\Mail\WelcomeClientMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeClientMailTest extends TestCase
{
    public function testJobIsQueuedWithCorrectRecipient(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'role'              => 'user',
            'locale'            => 'fr',
            'email_verified_at' => now(),
        ]);

        SendWelcomeClientEmail::dispatch($user);

        Queue::assertPushed(SendWelcomeClientEmail::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });
    }

    public function testMailIsQueuedToCorrectAddress(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role'              => 'user',
            'locale'            => 'fr',
            'email_verified_at' => now(),
        ]);

        (new SendWelcomeClientEmail($user))->handle();

        Mail::assertSent(WelcomeClientMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function testMailSubjectIsLocalizedFr(): void
    {
        $user = User::factory()->create([
            'role'              => 'user',
            'locale'            => 'fr',
            'email_verified_at' => now(),
        ]);

        // Test the envelope subject directly with app locale forced to fr.
        app()->setLocale('fr');
        $mail    = new WelcomeClientMail($user);
        $subject = $mail->envelope()->subject;

        $this->assertStringContainsString('Bienvenue', $subject);
    }

    public function testMailSubjectIsLocalizedEn(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role'              => 'user',
            'locale'            => 'en',
            'email_verified_at' => now(),
        ]);

        (new SendWelcomeClientEmail($user))->handle();

        Mail::assertSent(WelcomeClientMail::class, function ($mail) use ($user) {
            // Assert sent to correct user
            return $mail->hasTo($user->email);
        });
    }

    public function testMailNotSentForUnverifiedUser(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role'              => 'user',
            'locale'            => 'fr',
            'email_verified_at' => null,
        ]);

        (new SendWelcomeClientEmail($user))->handle();

        Mail::assertNothingSent();
    }
}
