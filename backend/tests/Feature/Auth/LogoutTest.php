<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    public function testUserCanLogout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(204);
    }

    public function testLogoutDeletesCurrentAccessToken(): void
    {
        $user = User::factory()->create();

        $tokenModel = $user->createToken('access_token');
        $token = $tokenModel->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertStatus(204);

        // Access token must be gone
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenModel->accessToken->id,
        ]);
    }

    public function testLogoutRevokesActiveRefreshTokens(): void
    {
        $user = User::factory()->create();

        $accessToken = $user->createToken('access_token');

        // Create two refresh tokens, one revoked and one active
        RefreshToken::create([
            'user_id'         => $user->id,
            'token'           => hash('sha256', Str::random(64)),
            'access_token_id' => $accessToken->accessToken->id,
            'expires_at'      => now()->addDays(90),
        ]);

        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(90),
            'revoked_at' => now()->subHour(), // already revoked
        ]);

        $this->withToken($accessToken->plainTextToken)->postJson('/api/auth/logout');

        // Active refresh token should now be revoked
        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id'    => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function testLogoutFailsWithoutAuth(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function testLogoutFailsWithInvalidToken(): void
    {
        $response = $this->withToken('invalid-token')->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
