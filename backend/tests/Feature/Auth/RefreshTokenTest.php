<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    public function testCanRefreshTokens(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);

        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15));

        RefreshToken::create([
            'user_id'         => $user->id,
            'token'           => hash('sha256', $plainToken),
            'access_token_id' => $accessToken->accessToken->id,
            'expires_at'      => now()->addDays(90),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'refresh_token',
                    'user' => ['id', 'email'],
                ],
            ]);
    }

    public function testRefreshRotatesTokens(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);

        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15));

        RefreshToken::create([
            'user_id'         => $user->id,
            'token'           => hash('sha256', $plainToken),
            'access_token_id' => $accessToken->accessToken->id,
            'expires_at'      => now()->addDays(90),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertOk();

        // New tokens must differ from the originals
        $this->assertNotEquals($plainToken, $response->json('data.refresh_token'));
        $this->assertNotEquals($accessToken->plainTextToken, $response->json('data.token'));
    }

    public function testOldRefreshTokenIsRevokedAfterRefresh(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);
        $hashed = hash('sha256', $plainToken);

        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15));

        RefreshToken::create([
            'user_id'         => $user->id,
            'token'           => $hashed,
            'access_token_id' => $accessToken->accessToken->id,
            'expires_at'      => now()->addDays(90),
        ]);

        $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        // The original token should now be revoked
        $record = RefreshToken::where('token', $hashed)->first();
        $this->assertNotNull($record->revoked_at);
    }

    public function testRefreshFailsWithInvalidToken(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors.refresh_token.0', 'invalid_or_expired');
    }

    public function testRefreshFailsWithExpiredToken(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);

        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plainToken),
            'expires_at' => now()->subDay(), // expired yesterday
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors.refresh_token.0', 'invalid_or_expired');
    }

    public function testRefreshFailsWithRevokedToken(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);

        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(90),
            'revoked_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $plainToken,
        ]);

        $response->assertStatus(401);
    }

    public function testRefreshFailsWithMissingToken(): void
    {
        $response = $this->postJson('/api/auth/refresh', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['refresh_token']);
    }
}
