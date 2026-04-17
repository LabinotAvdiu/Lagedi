<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

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

class CompanySlotsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCompany(array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name'    => 'Salon Test',
            'address' => '1 Rue Test',
            'city'    => 'Paris',
            'gender'  => 'both',
        ], $attributes));
    }

    /**
     * Creates an opening hour record for a given date's day of week.
     * DayOfWeek enum: 0=Monday … 6=Sunday
     * Carbon::dayOfWeek: 0=Sunday, 1=Monday … 6=Saturday
     */
    private function openOn(Company $company, Carbon $date, string $openTime = '09:00:00', string $closeTime = '17:00:00'): void
    {
        $carbonDow = (int) $date->dayOfWeek;
        $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;

        CompanyOpeningHour::updateOrCreate(
            ['company_id' => $company->id, 'day_of_week' => $enumDow],
            ['open_time' => $openTime, 'close_time' => $closeTime, 'is_closed' => false]
        );
    }

    private function closeOn(Company $company, Carbon $date): void
    {
        $carbonDow = (int) $date->dayOfWeek;
        $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;

        CompanyOpeningHour::updateOrCreate(
            ['company_id' => $company->id, 'day_of_week' => $enumDow],
            ['is_closed' => true]
        );
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
            'name'       => 'Service Test',
            'price'      => 30.00,
            'duration'   => $durationMinutes,
            'is_active'  => true,
        ]);
    }

    private function bookSlot(
        Company $company,
        CompanyUser $employee,
        string $date,
        string $startTime,
        string $endTime,
        string $status = 'confirmed'
    ): Appointment {
        $service = $this->createService($company);
        $user    = User::factory()->create();

        return Appointment::create([
            'user_id'         => $user->id,
            'company_id'      => $company->id,
            'company_user_id' => $employee->id,
            'service_id'      => $service->id,
            'date'            => $date,
            'start_time'      => $startTime,
            'end_time'        => $endTime,
            'status'          => $status,
        ]);
    }

    private function auth(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    // -------------------------------------------------------------------------
    // Slots — Authentication
    // -------------------------------------------------------------------------

    public function testSlotsIsPublicRoute(): void
    {
        // Route is intentionally public (no auth:sanctum) — guest access allowed.
        $company = $this->createCompany();
        $date    = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}");

        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // Slots — 404 / basic structure
    // -------------------------------------------------------------------------

    public function testSlotsReturns404ForUnknownCompany(): void
    {
        $this->auth();
        $date = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->getJson("/api/companies/99999/slots?date={$date}");

        $response->assertStatus(404);
    }

    public function testSlotsDateParamIsOptional(): void
    {
        $this->auth();
        $company = $this->createCompany();

        // date is now optional — defaults to today, returns 200 with an empty data
        // array (no opening hours defined for this company)
        $response = $this->getJson("/api/companies/{$company->id}/slots");

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    // -------------------------------------------------------------------------
    // Slots — Closed days
    // -------------------------------------------------------------------------

    public function testSlotsReturnsEmptyArrayWhenCompanyIsClosed(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();

        $this->closeOn($company, $tomorrow);

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$tomorrow->format('Y-m-d')}");

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function testSlotsReturnsEmptyWhenNoOpeningHourRecord(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$tomorrow}");

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    // -------------------------------------------------------------------------
    // Slots — Basic slot generation
    // -------------------------------------------------------------------------

    public function testSlotsAreGeneratedIn30MinuteIncrements(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        // Only open on tomorrow's day-of-week → only that day produces slots
        $this->openOn($company, $tomorrow, '09:00:00', '11:00:00');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}");

        $response->assertOk();
        $slots = $response->json('data');

        // Filter to just tomorrow's slots — the endpoint now spans 14 days
        // but this company only has hours for one day-of-week.
        $daySlots = array_values(
            array_filter($slots, fn ($s) => str_starts_with($s['dateTime'], $date))
        );

        // 09:00 to 11:00 = 4 slots (09:00, 09:30, 10:00, 10:30)
        $this->assertCount(4, $daySlots);

        $times = array_column($daySlots, 'dateTime');
        $this->assertStringEndsWith('T09:00:00', $times[0]);
        $this->assertStringEndsWith('T09:30:00', $times[1]);
        $this->assertStringEndsWith('T10:00:00', $times[2]);
        $this->assertStringEndsWith('T10:30:00', $times[3]);
    }

    public function testSlotsShapeMatchesFlutterExpectation(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();

        $this->openOn($company, $tomorrow, '09:00:00', '09:30:00');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$tomorrow->format('Y-m-d')}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['dateTime', 'employeeId'],
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // Slots — With specific employee_id
    // -------------------------------------------------------------------------

    public function testSlotsWithEmployeeIdExcludesHisBookedSlots(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        $this->openOn($company, $tomorrow, '09:00:00', '11:00:00');
        $employee = $this->createActiveEmployee($company);

        // Book employee for 09:00 – 10:00
        $this->bookSlot($company, $employee, $date, '09:00:00', '10:00:00');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}&employee_id={$employee->id}");

        $response->assertOk();
        $slots = $response->json('data');
        $times = array_column($slots, 'dateTime');

        // 09:00 and 09:30 are blocked (inside 09:00–10:00 window)
        $this->assertNotContains("{$date}T09:00:00", $times);
        $this->assertNotContains("{$date}T09:30:00", $times);
        // 10:00 and 10:30 are free
        $this->assertContains("{$date}T10:00:00", $times);
        $this->assertContains("{$date}T10:30:00", $times);
    }

    public function testSlotsWithEmployeeIdIncludesEmployeeIdInResponse(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();

        $this->openOn($company, $tomorrow, '09:00:00', '09:30:00');
        $employee = $this->createActiveEmployee($company);

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$tomorrow->format('Y-m-d')}&employee_id={$employee->id}");

        $response->assertOk();
        $slots = $response->json('data');

        $this->assertNotEmpty($slots);
        $this->assertEquals((string) $employee->id, $slots[0]['employeeId']);
    }

    public function testSlotsWithEmployeeIdDoesNotBlockSlotForOtherEmployee(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        $this->openOn($company, $tomorrow, '09:00:00', '11:00:00');
        $employee1 = $this->createActiveEmployee($company);
        $employee2 = $this->createActiveEmployee($company);

        // Book employee2, not employee1
        $this->bookSlot($company, $employee2, $date, '09:00:00', '10:00:00');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}&employee_id={$employee1->id}");

        $response->assertOk();
        $times = array_column($response->json('data'), 'dateTime');

        // employee1 is free — 09:00 should appear
        $this->assertContains("{$date}T09:00:00", $times);
    }

    public function testSlotsWithEmployeeIdIgnoresCancelledBookings(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        $this->openOn($company, $tomorrow, '09:00:00', '10:00:00');
        $employee = $this->createActiveEmployee($company);

        // Cancelled booking must not block the slot
        $this->bookSlot($company, $employee, $date, '09:00:00', '09:30:00', 'cancelled');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}&employee_id={$employee->id}");

        $response->assertOk();
        $times = array_column($response->json('data'), 'dateTime');
        $this->assertContains("{$date}T09:00:00", $times);
    }

    // -------------------------------------------------------------------------
    // Slots — "Sans préférence" logic (no employee_id)
    // -------------------------------------------------------------------------

    public function testSlotsWithoutEmployeeIdReturnsNullEmployeeId(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();

        $this->openOn($company, $tomorrow, '09:00:00', '09:30:00');
        $this->createActiveEmployee($company);

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$tomorrow->format('Y-m-d')}");

        $response->assertOk();
        $slots = $response->json('data');
        $this->assertNotEmpty($slots);
        $this->assertNull($slots[0]['employeeId']);
    }

    public function testSlotsWithoutEmployeeIdSlotAvailableWhenAtLeastOneEmployeeFree(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        $this->openOn($company, $tomorrow, '09:00:00', '10:00:00');
        $employee1 = $this->createActiveEmployee($company);
        $employee2 = $this->createActiveEmployee($company);

        // Only employee1 is booked; employee2 is still free
        $this->bookSlot($company, $employee1, $date, '09:00:00', '09:30:00');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}");

        $response->assertOk();
        $times = array_column($response->json('data'), 'dateTime');

        // Slot must still appear since employee2 is free
        $this->assertContains("{$date}T09:00:00", $times);
    }

    public function testSlotsWithoutEmployeeIdSlotHiddenWhenAllEmployeesBooked(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        $this->openOn($company, $tomorrow, '09:00:00', '10:00:00');
        $employee1 = $this->createActiveEmployee($company);
        $employee2 = $this->createActiveEmployee($company);

        // Both employees booked for 09:00 – 09:30
        $this->bookSlot($company, $employee1, $date, '09:00:00', '09:30:00');
        $this->bookSlot($company, $employee2, $date, '09:00:00', '09:30:00');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}");

        $response->assertOk();
        $times = array_column($response->json('data'), 'dateTime');

        // 09:00 hidden — all employees are booked
        $this->assertNotContains("{$date}T09:00:00", $times);
        // 09:30 is still available (window ended)
        $this->assertContains("{$date}T09:30:00", $times);
    }

    public function testSlotsWithoutEmployeeIdAllSlotsAvailableWhenNoBookings(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        // Only open on tomorrow's day-of-week → only that day produces slots
        $this->openOn($company, $tomorrow, '09:00:00', '11:00:00');
        $this->createActiveEmployee($company);
        $this->createActiveEmployee($company);

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}");

        $response->assertOk();

        // Filter to just tomorrow's slots — the endpoint now spans 14 days
        $daySlots = array_filter(
            $response->json('data'),
            fn ($s) => str_starts_with($s['dateTime'], $date)
        );

        $this->assertCount(4, $daySlots);
    }

    public function testSlotsWithoutEmployeeIdIgnoresPendingStatusAsBooked(): void
    {
        $this->auth();
        $company  = $this->createCompany();
        $tomorrow = Carbon::tomorrow();
        $date     = $tomorrow->format('Y-m-d');

        $this->openOn($company, $tomorrow, '09:00:00', '10:00:00');
        $employee1 = $this->createActiveEmployee($company);
        $employee2 = $this->createActiveEmployee($company);

        // Pending bookings also count as "booked" for slot blocking
        $this->bookSlot($company, $employee1, $date, '09:00:00', '09:30:00', 'pending');
        $this->bookSlot($company, $employee2, $date, '09:00:00', '09:30:00', 'pending');

        $response = $this->getJson("/api/companies/{$company->id}/slots?date={$date}");

        $times = array_column($response->json('data'), 'dateTime');
        $this->assertNotContains("{$date}T09:00:00", $times);
    }
}
