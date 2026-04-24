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

class CapacityAutoApproveTest extends TestCase
{
    private User $owner;
    private User $client;
    private Company $company;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->client = User::factory()->create();

        $this->company = Company::create([
            'name'         => 'Auto-approve Salon',
            'address'      => '1 Rue Test',
            'city'         => 'Paris',
            'booking_mode' => BookingMode::CapacityBased->value,
            // capacity_auto_approve defaults to false — do NOT set here
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id'    => $this->owner->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $this->service = Service::create([
            'company_id'     => $this->company->id,
            'name'           => 'Coupe',
            'price'          => 30.00,
            'duration'       => 30,
            'is_active'      => true,
            'max_concurrent' => 3,
        ]);
    }

    private function futureDateTime(int $daysAhead = 1, string $time = '10:00:00'): string
    {
        return Carbon::today()->addDays($daysAhead)->format('Y-m-d') . 'T' . $time;
    }

    // =========================================================================
    // Test 1 — capacity_based WITH auto_approve → status = confirmed
    // =========================================================================

    public function testCapacityBasedWithAutoApproveCreatesConfirmedAppointment(): void
    {
        $this->company->update(['capacity_auto_approve' => true]);

        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'user_id'    => $this->client->id,
            'status'     => AppointmentStatus::Confirmed->value,
        ]);

        // Response shape should expose status = confirmed
        $response->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);
    }

    // =========================================================================
    // Test 2 — capacity_based WITHOUT auto_approve → status = pending (regression)
    // =========================================================================

    public function testCapacityBasedWithoutAutoApproveStillCreatesPendingAppointment(): void
    {
        // capacity_auto_approve is false by default — no update needed.

        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'user_id'    => $this->client->id,
            'status'     => AppointmentStatus::Pending->value,
        ]);

        $response->assertJsonPath('data.status', AppointmentStatus::Pending->value);
    }

    // =========================================================================
    // Test 3 — employee_based ignores capacity_auto_approve entirely
    // =========================================================================

    public function testEmployeeBasedIgnoresCapacityAutoApprove(): void
    {
        // Switch to employee_based AND enable the flag — flag must be ignored.
        $this->company->update([
            'booking_mode'          => BookingMode::EmployeeBased->value,
            'capacity_auto_approve' => true,
        ]);

        // Need an active employee linked to the service for auto-assign to work.
        $employee = User::factory()->create();
        $pivot = CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id'    => $employee->id,
            'role'       => CompanyRole::Employee->value,
            'is_active'  => true,
        ]);
        $pivot->services()->attach($this->service->id, ['duration' => null]);

        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(201);

        // employee_based bookings are ALWAYS confirmed regardless of the flag.
        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'user_id'    => $this->client->id,
            'status'     => AppointmentStatus::Confirmed->value,
        ]);
    }

    // =========================================================================
    // Test 4 — owner can toggle capacity_auto_approve via PATCH booking-settings
    // =========================================================================

    public function testOwnerCanToggleCapacityAutoApprove(): void
    {
        Sanctum::actingAs($this->owner);

        // Enable
        $this->putJson('/api/my-company/booking-settings', [
            'capacity_auto_approve' => true,
        ])->assertOk()
          ->assertJsonPath('capacityAutoApprove', true);

        $this->assertDatabaseHas('companies', [
            'id'                    => $this->company->id,
            'capacity_auto_approve' => true,
        ]);

        // Disable
        $this->putJson('/api/my-company/booking-settings', [
            'capacity_auto_approve' => false,
        ])->assertOk()
          ->assertJsonPath('capacityAutoApprove', false);

        $this->assertDatabaseHas('companies', [
            'id'                    => $this->company->id,
            'capacity_auto_approve' => false,
        ]);
    }

    // =========================================================================
    // Test 5 — MyCompanyResource exposes capacityAutoApprove
    // =========================================================================

    public function testMyCompanyResourceExposesCapacityAutoApprove(): void
    {
        $this->company->update(['capacity_auto_approve' => true]);

        Sanctum::actingAs($this->owner);

        $this->getJson('/api/my-company')
            ->assertOk()
            ->assertJsonPath('capacityAutoApprove', true);
    }
}
