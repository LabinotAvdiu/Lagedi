<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegisterTest extends TestCase
{
    // ─── Données valides de base ───────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'       => 'Dupont',
            'first_name' => 'Jean',
            'email'      => 'jean@example.com',
            'password'   => 'password123',
            'phone'      => '0612345678',
        ], $overrides);
    }

    // ─── Cas qui marche ───────────────────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'first_name', 'email', 'phone'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jean@example.com',
            'name'  => 'Dupont',
        ]);
    }

    public function test_user_can_register_without_first_name(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'first_name' => null,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'jean@example.com']);
    }

    // ─── Champs obligatoires manquants ────────────────────────────────────────

    public function test_register_fails_without_name(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'name' => '',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_register_fails_without_email(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'email' => '',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'email' => 'pas-un-email',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_without_password(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'password' => '',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'password' => '123',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_without_phone(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'phone' => '',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // ─── Email déjà utilisé ───────────────────────────────────────────────────

    public function test_register_fails_with_duplicate_email(): void
    {
        $payload = $this->validPayload();

        // Premier register → OK
        $this->postJson('/api/auth/register', $payload)->assertStatus(201);

        // Deuxième avec le même email → doit échouer
        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Réponse ne doit pas exposer le mot de passe ──────────────────────────

    public function test_register_response_does_not_expose_password(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }
}

