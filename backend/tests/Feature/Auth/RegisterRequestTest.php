<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterRequestTest extends TestCase
{
    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'     => 'Marie',
            'last_name'      => 'Curie',
            'email'          => 'marie@example.com',
            'password'       => 'Password1',
            'role'           => 'company',
            'company_name'   => 'Salon Test',
            'company_gender' => 'both',
            'address'        => '1 Rue Test',
        ], $overrides);
    }

    public function testBookingModeDefaultsToEmployeeBased(): void
    {
        Notification::fake();
        Mail::fake();

        $this->postJson('/api/auth/register', $this->companyPayload());

        $this->assertDatabaseHas('companies', [
            'name'         => 'Salon Test',
            'booking_mode' => 'employee_based',
        ]);
    }

    public function testBookingModeCapacityBasedIsAccepted(): void
    {
        Notification::fake();
        Mail::fake();

        $this->postJson('/api/auth/register', $this->companyPayload([
            'booking_mode' => 'capacity_based',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('companies', [
            'name'         => 'Salon Test',
            'booking_mode' => 'capacity_based',
        ]);
    }

    public function testInvalidBookingModeReturns422(): void
    {
        $this->postJson('/api/auth/register', $this->companyPayload([
            'booking_mode' => 'invalid_mode',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['booking_mode']);
    }
}
