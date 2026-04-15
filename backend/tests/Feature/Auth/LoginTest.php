<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'      => 'Dupont',
            'email'     => 'jean@example.com',
            'password'  => 'password123',
            'phone'     => '0612345678',
            'api_token' => \Illuminate\Support\Str::random(80),
        ], $overrides));
    }

    // ─── Cas qui marche ───────────────────────────────────────────────────────

    public function test_user_can_login(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone'],
                'token',
            ]);
    }

    public function test_login_returns_a_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('token'));
    }

    // ─── Mauvais identifiants ─────────────────────────────────────────────────

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'inconnu@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Champs obligatoires manquants ────────────────────────────────────────

    public function test_login_fails_without_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => '',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_without_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'pas-un-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Réponse ne doit pas exposer le mot de passe ──────────────────────────

    public function test_login_response_does_not_expose_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }
}
