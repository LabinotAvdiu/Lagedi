<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'email'    => 'jean@example.com',
            'password' => 'password123',
        ]);
    }

    public function testUserCanLogin(): void
    {
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

    public function testLoginReturnsAToken(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('token'));
    }

    public static function invalidLoginPayloads(): array
    {
        return [
            'empty email' => [
                ['email' => '', 'password' => 'password123'],
                'email',
            ],
            'empty password' => [
                ['email' => 'jean@example.com', 'password' => ''],
                'password',
            ],
            'invalid email format' => [
                ['email' => 'pas-un-email', 'password' => 'password123'],
                'email',
            ],
            'wrong password' => [
                ['email' => 'jean@example.com', 'password' => 'wrong-pass'],
                'email',
            ],
            'unknown email' => [
                ['email' => 'unknown@example.com', 'password' => 'password123'],
                'email',
            ],
        ];
    }

    #[DataProvider('invalidLoginPayloads')]
    public function testLoginFailsWithInvalidData(array $payload, string $errorField): void
    {
        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$errorField]);
    }

    public function testLoginResponseDoesNotExposePassword(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'jean@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }
}
