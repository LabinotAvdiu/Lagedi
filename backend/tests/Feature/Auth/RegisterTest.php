<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegisterTest extends TestCase
{
    public function testUserCanRegister(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'password' => 'password123',
            'phone' => '0612345678',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'first_name', 'email', 'phone'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jean@example.com',
            'name' => 'Dupont',
        ]);
    }
}
