<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    // -------------------------------------------------------------------------
    // POST /api/auth/forgot-password
    // -------------------------------------------------------------------------

    public function testForgotPasswordReturns200ForExistingEmail(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jean@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function testForgotPasswordSendsResetNotificationForExistingUser(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jean@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'jean@example.com',
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function testForgotPasswordReturns200ForNonExistentEmailAntiEnumeration(): void
    {
        // Must return 200 even for unknown emails to prevent email enumeration
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function testForgotPasswordDoesNotSendNotificationForUnknownEmail(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function testForgotPasswordFailsWithMissingEmail(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testForgotPasswordFailsWithInvalidEmailFormat(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/reset-password
    // -------------------------------------------------------------------------

    public function testCanResetPasswordWithValidToken(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jean@example.com']);

        // Trigger the reset link to get a real broker token
        Password::sendResetLink(['email' => 'jean@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            return true;
        });

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(200);

        // Verify the new password hash is different (password was changed)
        $updatedUser = $user->fresh();
        $this->assertTrue(Hash::check('NewPassword1', $updatedUser->password));
    }

    public function testResetPasswordClearsAccountLockout(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email'                  => 'jean@example.com',
            'failed_login_attempts'  => 10,
            'locked_until'           => now()->addMinutes(30),
        ]);

        Password::sendResetLink(['email' => 'jean@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $this->assertDatabaseHas('users', [
            'id'                    => $user->id,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    public function testResetPasswordFailsWithInvalidToken(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => 'invalid-reset-token',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'invalid_or_expired');
    }

    public function testResetPasswordFailsWithWeakPassword(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => 'sometoken',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function testResetPasswordFailsWithMismatchedConfirmation(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => 'sometoken',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'DifferentPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function testResetPasswordFailsWithMissingFields(): void
    {
        $response = $this->postJson('/api/auth/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/verify-email
    // -------------------------------------------------------------------------

    public function testCanVerifyEmailWithValidToken(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'jean@example.com',
        ]);

        $plainToken = 'ABC123';
        \DB::table('email_verification_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => \Hash::make($plainToken),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'jean@example.com',
            'token' => $plainToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'email']]);

        // Email is now verified
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function testVerifyEmailCleansUpTokenRecord(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'jean@example.com',
        ]);

        $plainToken = 'ABC123';
        \DB::table('email_verification_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => \Hash::make($plainToken),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);

        $this->postJson('/api/auth/verify-email', [
            'email' => 'jean@example.com',
            'token' => $plainToken,
        ]);

        $this->assertDatabaseMissing('email_verification_tokens', [
            'email' => 'jean@example.com',
        ]);
    }

    public function testVerifyEmailFailsWithInvalidToken(): void
    {
        User::factory()->unverified()->create(['email' => 'jean@example.com']);

        \DB::table('email_verification_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => \Hash::make('ABC123'),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'jean@example.com',
            'token' => 'WRONGTOKEN',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'invalid_or_expired');
    }

    public function testVerifyEmailFailsWithExpiredToken(): void
    {
        User::factory()->unverified()->create(['email' => 'jean@example.com']);

        $plainToken = 'ABC123';
        \DB::table('email_verification_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => \Hash::make($plainToken),
            'expires_at' => now()->subHour(), // already expired
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'jean@example.com',
            'token' => $plainToken,
        ]);

        $response->assertStatus(422);
    }

    public function testVerifyEmailFailsWithMissingFields(): void
    {
        $response = $this->postJson('/api/auth/verify-email', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token']);
    }
}
