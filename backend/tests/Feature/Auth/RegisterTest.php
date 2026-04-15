<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
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

    public function testUserCanRegister(): void
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

    public function testUserCanRegisterWithoutFirstName(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'first_name' => null,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'jean@example.com']);
    }

    public static function invalidRegisterPayloads(): array
    {
        return [
            'missing name' => [
                ['name' => ''],
                'name',
            ],
            'missing email' => [
                ['email' => ''],
                'email',
            ],
            'invalid email' => [
                ['email' => 'pas-un-email'],
                'email',
            ],
            'missing password' => [
                ['password' => ''],
                'password',
            ],
            'short password' => [
                ['password' => '123'],
                'password',
            ],
            'missing phone' => [
                ['phone' => ''],
                'phone',
            ],
        ];
    }

    #[DataProvider('invalidRegisterPayloads')]
    public function testRegisterFailsWithInvalidPayload(array $override, string $errorField): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload($override));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$errorField]);
    }

    public function testRegisterFailsWithDuplicateEmail(): void
    {
        $payload = $this->validPayload();
        User::factory()->create(['email' => $payload['email']]);

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testRegisterResponseDoesNotExposePassword(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }
}
