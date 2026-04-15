<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
            'email'      => 'jean@example.com',
            'password'   => 'Password1',
            'phone'      => '0612345678',
        ], $overrides);
    }

    public function testUserCanRegister(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'refresh_token',
                    'user' => ['id', 'email', 'firstName', 'lastName'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email'      => 'jean@example.com',
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
        ]);
    }

    public function testCompanyRegistrationCreatesCompanyAndEmployee(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'role'         => 'company',
            'company_name' => 'Salon Élégance',
            'address'      => '39 Rue de la Bourse',
            'city'         => 'Paris',
        ]));

        $response->assertStatus(201);

        // User created with company role
        $this->assertDatabaseHas('users', [
            'email' => 'jean@example.com',
            'role'  => 'company',
        ]);

        // Company created
        $this->assertDatabaseHas('companies', [
            'name'    => 'Salon Élégance',
            'address' => '39 Rue de la Bourse',
            'city'    => 'Paris',
        ]);

        // User is attached as owner in the pivot table
        $user    = User::where('email', 'jean@example.com')->first();
        $company = \App\Models\Company::where('name', 'Salon Élégance')->first();

        $this->assertNotNull($company);
        $this->assertDatabaseHas('company_user', [
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);
    }

    public function testCompanyRegistrationRequiresCompanyName(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'role'    => 'company',
            'address' => '39 Rue de la Bourse',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);
    }

    public function testCompanyRegistrationRequiresAddress(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'role'         => 'company',
            'company_name' => 'Salon Élégance',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    public function testRegisterFailsWithDuplicateEmail(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testRegisterFailsWithWeakPasswordTooShort(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'password' => '123',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function testRegisterFailsWithPasswordWithoutUppercase(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'password' => 'password1',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function testRegisterFailsWithPasswordWithoutNumbers(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'password' => 'PasswordOnly',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function testRegisterFailsWithMissingRequiredFields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'first_name', 'last_name']);
    }

    public function testRegisterFailsWithInvalidEmailFormat(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'email' => 'not-an-email',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testRegisterResponseDoesNotExposePassword(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function testRegisterIssuesBothAccessAndRefreshToken(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.refresh_token'));
    }

    public function testRegisterWithRoleUserDoesNotCreateCompany(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->validPayload(['role' => 'user']));

        $this->assertDatabaseCount('companies', 0);
    }
}
