<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateBookingTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCompany(): Company
    {
        return Company::create([
            'name'    => 'Salon Test',
            'address' => '1 Rue Test',
            'city'    => 'Paris',
            'gender'  => 'both',
        ]);
    }

    private function createActiveEmployee(Company $company): CompanyUser
    {
        $user = User::factory()->create();
        return CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'role'       => 'employee',
            'is_active'  => true,
        ]);
    }

    private function createService(Company $company, int $durationMinutes = 60): Service
    {
        return Service::create([
            'company_id' => $company->id,
            'name'       => 'Coupe homme',
            'price'      => 25.00,
            'duration'   => $durationMinutes,
            'is_active'  => true,
        ]);
    }

    /** Returns an ISO datetime string well in the future (safe for "after:now" validation). */
    private function futureDateTime(int $daysAhead = 1, string $time = '10:00:00'): string
    {
        return Carbon::today()->addDays($daysAhead)->format('Y-m-d') . 'T' . $time;
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function testCreateBookingRequiresAuth(): void
    {
        $response = $this->postJson('/api/bookings', []);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Booking with explicit employee
    // -------------------------------------------------------------------------

    public function testCanCreateBookingWithExplicitEmployee(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 60);
        $dateTime = $this->futureDateTime();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'company_id'  => $company->id,
            'service_id'  => $service->id,
            'employee_id' => $employee->id,
            'date_time'   => $dateTime,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id', 'companyId', 'serviceId', 'employeeId', 'dateTime', 'status',
                ],
            ]);

        $this->assertDatabaseHas('appointments', [
            'user_id'         => $user->id,
            'company_id'      => $company->id,
            'service_id'      => $service->id,
            'company_user_id' => $employee->id,
        ]);
    }

    public function testBookingWithEmployeeStoresCorrectStartAndEndTime(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 30);

        Sanctum::actingAs($user);

        $date = Carbon::today()->addDay()->format('Y-m-d');
        $this->postJson('/api/bookings', [
            'company_id'  => $company->id,
            'service_id'  => $service->id,
            'employee_id' => $employee->id,
            'date_time'   => "{$date}T10:00:00",
        ]);

        $this->assertDatabaseHas('appointments', [
            'company_user_id' => $employee->id,
            'date'            => $date,
            'start_time'      => '10:00:00',
            'end_time'        => '10:30:00', // 30-minute service
        ]);
    }

    public function testBookingResponseContainsCorrectEmployeeId(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 30);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'company_id'  => $company->id,
            'service_id'  => $service->id,
            'employee_id' => $employee->id,
            'date_time'   => $this->futureDateTime(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.employeeId', (string) $employee->id);
    }

    public function testBookingStatusIsConfirmedByDefault(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 30);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'company_id'  => $company->id,
            'service_id'  => $service->id,
            'employee_id' => $employee->id,
            'date_time'   => $this->futureDateTime(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');
    }

    // -------------------------------------------------------------------------
    // Booking without employee — auto-assign ("sans préférence")
    // -------------------------------------------------------------------------

    public function testCanCreateBookingWithoutEmployeeAutoAssigns(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 60);
        $dateTime = $this->futureDateTime();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date_time'  => $dateTime,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Should have been assigned to the only active employee
        $this->assertDatabaseHas('appointments', [
            'user_id'         => $user->id,
            'company_user_id' => $employee->id,
        ]);
    }

    public function testAutoAssignPicksFirstFreeEmployee(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee1 = $this->createActiveEmployee($company);
        $employee2 = $this->createActiveEmployee($company);
        $service   = $this->createService($company, 60);

        $date      = Carbon::today()->addDay()->format('Y-m-d');
        $dateTime  = "{$date}T10:00:00";

        // Pre-book employee1 at the same slot
        $otherService = $this->createService($company, 60);
        $otherUser    = User::factory()->create();

        Appointment::create([
            'user_id'         => $otherUser->id,
            'company_id'      => $company->id,
            'service_id'      => $otherService->id,
            'company_user_id' => $employee1->id,
            'date'            => $date,
            'start_time'      => '10:00:00',
            'end_time'        => '11:00:00',
            'status'          => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date_time'  => $dateTime,
        ]);

        $response->assertStatus(201);

        // employee1 was busy → auto-assign should pick employee2
        $this->assertDatabaseHas('appointments', [
            'user_id'         => $user->id,
            'company_user_id' => $employee2->id,
        ]);
    }

    public function testAutoAssignReturns422WhenAllEmployeesBooked(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 60);

        $date      = Carbon::today()->addDay()->format('Y-m-d');
        $dateTime  = "{$date}T10:00:00";

        // Pre-book the only employee at the same slot
        $otherUser = User::factory()->create();
        Appointment::create([
            'user_id'         => $otherUser->id,
            'company_id'      => $company->id,
            'service_id'      => $service->id,
            'company_user_id' => $employee->id,
            'date'            => $date,
            'start_time'      => '10:00:00',
            'end_time'        => '11:00:00',
            'status'          => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date_time'  => $dateTime,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // -------------------------------------------------------------------------
    // Booked slot disappears from slots endpoint
    // -------------------------------------------------------------------------

    public function testBookedSlotDisappearsFromSlotsEndpointWhenAllEmployeesBooked(): void
    {
        $user     = User::factory()->create();
        $company  = $this->createCompany();
        $employee = $this->createActiveEmployee($company);
        $service  = $this->createService($company, 30);

        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        // Open the company on that day
        $carbonDow = (int) $tomorrow->dayOfWeek;
        $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;
        CompanyOpeningHour::create([
            'company_id'  => $company->id,
            'day_of_week' => $enumDow,
            'open_time'   => '09:00:00',
            'close_time'  => '10:00:00',
            'is_closed'   => false,
        ]);

        Sanctum::actingAs($user);

        // Slots before booking — 09:00 and 09:30 should be present
        $slotsBefore = $this->getJson("/api/companies/{$company->id}/slots?date={$date}")->json('data');
        $timesBefore = array_column($slotsBefore, 'dateTime');
        $this->assertContains("{$date}T09:00:00", $timesBefore);

        // Create the booking
        $this->postJson('/api/bookings', [
            'company_id'  => $company->id,
            'service_id'  => $service->id,
            'employee_id' => $employee->id,
            'date_time'   => "{$date}T09:00:00",
        ]);

        // Slots after booking — 09:00 is now blocked (all employees booked)
        $slotsAfter = $this->getJson("/api/companies/{$company->id}/slots?date={$date}&employee_id={$employee->id}")->json('data');
        $timesAfter = array_column($slotsAfter, 'dateTime');
        $this->assertNotContains("{$date}T09:00:00", $timesAfter);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function testCreateBookingFailsWithMissingCompanyId(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/bookings', [
            'service_id' => 1,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function testCreateBookingFailsWithMissingServiceId(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function testCreateBookingFailsWithMissingDateTime(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();
        $service = $this->createService($company);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_time']);
    }

    public function testCreateBookingFailsWithPastDateTime(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();
        $service = $this->createService($company);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date_time'  => Carbon::yesterday()->format('Y-m-d') . 'T10:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_time']);
    }

    public function testCreateBookingFailsWithInvalidDateTimeFormat(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();
        $service = $this->createService($company);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => $service->id,
            'date_time'  => '2026-01-01 10:00', // missing seconds and T separator
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_time']);
    }

    public function testCreateBookingFailsWithNonExistentCompanyId(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();
        $service = $this->createService($company);

        $response = $this->postJson('/api/bookings', [
            'company_id' => 99999,
            'service_id' => $service->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function testCreateBookingFailsWithNonExistentServiceId(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();

        $response = $this->postJson('/api/bookings', [
            'company_id' => $company->id,
            'service_id' => 99999,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function testCreateBookingFailsWithNonExistentEmployeeId(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = $this->createCompany();
        $service = $this->createService($company);

        $response = $this->postJson('/api/bookings', [
            'company_id'  => $company->id,
            'service_id'  => $service->id,
            'employee_id' => 99999,
            'date_time'   => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    }

    // -------------------------------------------------------------------------
    // Cross-company authorization — `exists:*` only checks row existence,
    // not ownership. These tests guard against an attacker booking with a
    // service/employee_id that belongs to a different company.
    // -------------------------------------------------------------------------

    public function testCreateBookingRejectsServiceFromOtherCompany(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $targetCompany = $this->createCompany();
        $otherCompany  = Company::create([
            'name' => 'Salon Other', 'address' => '2 Rue Other',
            'city' => 'Paris', 'gender' => 'both',
        ]);
        // The service exists, but it belongs to otherCompany, not target.
        $foreignService = $this->createService($otherCompany);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $targetCompany->id,
            'service_id' => $foreignService->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function testCreateBookingRejectsEmployeeFromOtherCompany(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $targetCompany = $this->createCompany();
        $otherCompany  = Company::create([
            'name' => 'Salon Other', 'address' => '2 Rue Other',
            'city' => 'Paris', 'gender' => 'both',
        ]);
        $targetService  = $this->createService($targetCompany);
        // The employee exists, but works at otherCompany, not target.
        $foreignEmployee = $this->createActiveEmployee($otherCompany);

        $response = $this->postJson('/api/bookings', [
            'company_id'  => $targetCompany->id,
            'service_id'  => $targetService->id,
            'employee_id' => $foreignEmployee->id,
            'date_time'   => $this->futureDateTime(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    }
}
