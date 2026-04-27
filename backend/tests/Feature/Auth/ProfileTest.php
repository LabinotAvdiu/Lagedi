<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function testUserCanGetProfile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'email', 'firstName', 'lastName'],
            ]);
    }

    public function testProfileReturnsCamelCaseShape(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
            'email'      => 'jean@example.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/profile');

        $response->assertOk()
            ->assertJsonPath('data.firstName', 'Jean')
            ->assertJsonPath('data.lastName', 'Dupont')
            ->assertJsonPath('data.email', 'jean@example.com');
    }

    public function testProfileReturnsAdminRoleWhenUserIsAdmin(): void
    {
        $user = User::factory()->create([
            'role' => \App\Enums\UserRole::Admin,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function testProfileDoesNotExposePassword(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/profile');

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function testProfileFailsWithoutAuth(): void
    {
        $response = $this->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    public function testUserCanUpdateProfile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.firstName', 'Updated')
            ->assertJsonPath('data.lastName', 'Name');

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => 'Updated',
            'last_name'  => 'Name',
        ]);
    }

    public function testUserCanUpdatePhone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'phone' => '0698765432',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'phone' => '0698765432',
        ]);
    }

    public function testUserCanUpdateCity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'city' => 'Lyon',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'city' => 'Lyon',
        ]);
    }

    public function testUpdateProfileFailsWithTooLongFirstName(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'first_name' => str_repeat('a', 101),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function testUpdateProfileFailsWithInvalidProfileImageUrl(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'profile_image_url' => 'not-a-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_image_url']);
    }

    public function testUpdateProfileCanClearNullableFields(): void
    {
        $user = User::factory()->create(['phone' => '0612345678']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'phone' => null,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'phone' => null,
        ]);
    }

    public function testUpdateProfileFailsWithoutAuth(): void
    {
        $response = $this->putJson('/api/auth/profile', [
            'first_name' => 'Updated',
        ]);

        $response->assertStatus(401);
    }

    public function testUpdateProfileDoesNotChangePatchedFieldsOfOtherUser(): void
    {
        $user1 = User::factory()->create(['first_name' => 'Alice']);
        $user2 = User::factory()->create(['first_name' => 'Bob']);

        Sanctum::actingAs($user1);

        $this->putJson('/api/auth/profile', ['first_name' => 'Changed']);

        // user2 must be unchanged
        $this->assertDatabaseHas('users', [
            'id'         => $user2->id,
            'first_name' => 'Bob',
        ]);
    }
}
