<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyCompanyBookingSettingsTest extends TestCase
{
    private function createOwnerWithCompany(string $mode = 'employee_based'): array
    {
        $user    = User::factory()->create(['role' => 'company']);
        $company = Company::create([
            'name'         => 'Test Salon',
            'address'      => '1 Rue Test',
            'city'         => 'Paris',
            'booking_mode' => $mode,
        ]);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        return [$user, $company];
    }

    public function testOwnerCanSwitchBookingMode(): void
    {
        [$user, $company] = $this->createOwnerWithCompany('employee_based');
        Sanctum::actingAs($user);

        $this->putJson('/api/my-company/booking-settings', [
            'booking_mode' => 'capacity_based',
        ])->assertStatus(200)
            ->assertJsonPath('bookingMode', 'capacity_based');

        $this->assertDatabaseHas('companies', [
            'id'           => $company->id,
            'booking_mode' => 'capacity_based',
        ]);
    }

    public function testSwitchingModePreservesExistingAppointments(): void
    {
        [$user, $company] = $this->createOwnerWithCompany('employee_based');
        Sanctum::actingAs($user);

        $service = Service::create([
            'company_id' => $company->id,
            'name'       => 'Coupe',
            'price'      => 25.00,
            'duration'   => 30,
            'is_active'  => true,
        ]);

        $client = User::factory()->create();
        Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
        ]);

        $this->putJson('/api/my-company/booking-settings', [
            'booking_mode' => 'capacity_based',
        ])->assertStatus(200);

        // Appointment must still exist with original status
        $this->assertDatabaseHas('appointments', [
            'company_id' => $company->id,
            'status'     => AppointmentStatus::Confirmed->value,
        ]);
    }

    public function testInvalidModeReturns422(): void
    {
        [$user] = $this->createOwnerWithCompany();
        Sanctum::actingAs($user);

        $this->putJson('/api/my-company/booking-settings', [
            'booking_mode' => 'invalid',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['booking_mode']);
    }
}
