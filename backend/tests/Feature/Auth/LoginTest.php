<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function testUserCanLogin(): void
    {
        User::factory()->create([
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'refresh_token',
                    'user' => ['id', 'email', 'firstName', 'lastName'],
                ],
            ]);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        User::factory()->create([
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors.email.0', 'invalid_credentials');
    }

    public function testLoginFailsWithNonExistentEmail(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'Password1',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors.email.0', 'invalid_credentials');
    }

    public function testLoginFailsWithMissingEmail(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'Password1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testLoginFailsWithMissingPassword(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'jean@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function testLoginFailsWithInvalidEmailFormat(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'Password1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testAccountLocksAfterTooManyFailedAttempts(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        User::factory()->create([
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'jean@example.com',
                'password' => 'WrongPassword1',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('errors.email.0', 'account_locked');
    }

    public function testLockedAccountCannotLoginEvenWithCorrectPassword(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        User::factory()->create([
            'email'        => 'jean@example.com',
            'password'     => 'Password1',
            'locked_until' => now()->addMinutes(30),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('errors.email.0', 'account_locked');
    }

    public function testSuccessfulLoginClearsFailedAttempts(): void
    {
        $user = User::factory()->create([
            'email'                  => 'jean@example.com',
            'password'               => 'Password1',
            'failed_login_attempts'  => 5,
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $this->assertDatabaseHas('users', [
            'id'                    => $user->id,
            'failed_login_attempts' => 0,
        ]);
    }

    public function testLoginResponseDoesNotExposePasswordHash(): void
    {
        User::factory()->create([
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function testLoginIssuesBothAccessAndRefreshToken(): void
    {
        User::factory()->create([
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'Password1',
        ]);

        $response->assertOk();

        $token        = $response->json('data.token');
        $refreshToken = $response->json('data.refresh_token');

        $this->assertNotEmpty($token);
        $this->assertNotEmpty($refreshToken);
        $this->assertNotEquals($token, $refreshToken);
    }
}
