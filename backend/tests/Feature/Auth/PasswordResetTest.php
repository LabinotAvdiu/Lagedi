<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    // -------------------------------------------------------------------------
    // POST /api/auth/forgot-password
    // -------------------------------------------------------------------------

    public function testForgotPasswordReturns200ForExistingEmail(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'jean@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function testForgotPasswordSendsResetMailForExistingUser(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'jean@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'jean@example.com',
        ]);

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) {
            return $mail->hasTo('jean@example.com');
        });
    }

    public function testForgotPasswordPersistsHashedTokenInDatabase(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'jean@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'jean@example.com',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', 'jean@example.com')
            ->first();

        $this->assertNotNull($record);
        $this->assertNotEmpty($record->token);
    }

    public function testForgotPasswordReturns200ForNonExistentEmailAntiEnumeration(): void
    {
        // Must return 200 even for unknown emails to prevent email enumeration
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function testForgotPasswordDoesNotSendMailForUnknownEmail(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        Mail::assertNothingSent();
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
        $user = User::factory()->create(['email' => 'jean@example.com']);

        $plainToken = 'R3SET1';
        DB::table('password_reset_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => $plainToken,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(200);

        $updatedUser = $user->fresh();
        $this->assertTrue(Hash::check('NewPassword1', $updatedUser->password));

        // Token record should be consumed
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'jean@example.com',
        ]);
    }

    public function testResetPasswordClearsAccountLockout(): void
    {
        $user = User::factory()->create([
            'email'                  => 'jean@example.com',
            'failed_login_attempts'  => 10,
            'locked_until'           => now()->addMinutes(30),
        ]);

        $plainToken = 'R3SET2';
        DB::table('password_reset_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => $plainToken,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $this->assertDatabaseHas('users', [
            'id'                    => $user->id,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    public function testResetPasswordFailsWithExpiredToken(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $plainToken = 'R3SET3';
        DB::table('password_reset_tokens')->insert([
            'email'      => 'jean@example.com',
            'token'      => Hash::make($plainToken),
            'created_at' => now()->subMinutes(61), // one minute past the 60-minute TTL
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'jean@example.com',
            'token'                 => $plainToken,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'expired');

        // Expired tokens are purged so a stale record can't be reused
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'jean@example.com',
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
