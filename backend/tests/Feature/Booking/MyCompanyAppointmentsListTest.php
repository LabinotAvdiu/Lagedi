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

class MyCompanyAppointmentsListTest extends TestCase
{
    private Company $company;
    private User $owner;
    private Service $service;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->today = Carbon::today()->format('Y-m-d');

        $this->owner = User::factory()->create(['role' => 'company']);

        $this->company = Company::create([
            'name'         => 'Test Salon',
            'address'      => '1 Rue Test',
            'city'         => 'Paris',
            'booking_mode' => BookingMode::CapacityBased->value,
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id'    => $this->owner->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $this->service = Service::create([
            'company_id'     => $this->company->id,
            'name'           => 'Coupe homme',
            'price'          => 15.00,
            'duration'       => 30,
            'is_active'      => true,
            'max_concurrent' => 3,
        ]);
    }

    private function makeAppointment(string $status, string $startTime = '10:00:00'): Appointment
    {
        $client = User::factory()->create();

        return Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $this->today,
            'start_time' => $startTime,
            'end_time'   => Carbon::parse($startTime)->addMinutes(30)->format('H:i:s'),
            'status'     => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testOwnerCanListAppointmentsForADate(): void
    {
        Sanctum::actingAs($this->owner);

        $confirmed = $this->makeAppointment(AppointmentStatus::Confirmed->value, '09:00:00');
        $pending   = $this->makeAppointment(AppointmentStatus::Pending->value,   '10:00:00');
        $cancelled = $this->makeAppointment(AppointmentStatus::Cancelled->value, '11:00:00');
        // Rejected is excluded by default (never happened).
        $this->makeAppointment(AppointmentStatus::Rejected->value, '12:00:00');

        $response = $this->getJson("/api/my-company/appointments?date={$this->today}");

        // Default filter keeps confirmed, pending, no-show and cancelled so
        // the owner's timeline stays faithful after marking no-shows.
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', (string) $confirmed->id)
            ->assertJsonPath('data.1.id', (string) $pending->id)
            ->assertJsonPath('data.2.id', (string) $cancelled->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'date', 'startTime', 'endTime', 'status',
                        'clientFirstName', 'clientLastName', 'clientPhone',
                        'service' => ['id', 'name', 'durationMinutes', 'price'],
                        'employeeName', 'isWalkIn',
                    ],
                ],
            ]);
    }

    public function testResponseFieldsHaveCorrectShape(): void
    {
        Sanctum::actingAs($this->owner);

        $appt = $this->makeAppointment(AppointmentStatus::Confirmed->value, '14:30:00');

        $response = $this->getJson("/api/my-company/appointments?date={$this->today}");

        $response->assertStatus(200);
        $item = $response->json('data.0');

        $this->assertSame($this->today, $item['date']);
        $this->assertSame('14:30', $item['startTime']);
        $this->assertSame('confirmed', $item['status']);
        $this->assertSame('Coupe homme', $item['service']['name']);
        $this->assertSame(30, $item['service']['durationMinutes']);
        $this->assertEqualsWithDelta(15.0, $item['service']['price'], 0.001);
        $this->assertNull($item['employeeName']); // Type 2 — no employee
        $this->assertFalse($item['isWalkIn']);
    }

    // -------------------------------------------------------------------------
    // Validation — date missing → 422
    // -------------------------------------------------------------------------

    public function testMissingDateReturns422(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/my-company/appointments')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function testInvalidDateFormatReturns422(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/my-company/appointments?date=17-04-2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    // -------------------------------------------------------------------------
    // Authorization — non-owner → 403
    // -------------------------------------------------------------------------

    public function testNonOwnerReceives403(): void
    {
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->getJson("/api/my-company/appointments?date={$this->today}")
            ->assertStatus(403);
    }

    public function testUnauthenticatedReceives401(): void
    {
        $this->getJson("/api/my-company/appointments?date={$this->today}")
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Status filter respected
    // -------------------------------------------------------------------------

    public function testStatusFilterReturnsOnlyRequestedStatuses(): void
    {
        Sanctum::actingAs($this->owner);

        $this->makeAppointment(AppointmentStatus::Confirmed->value,  '09:00:00');
        $this->makeAppointment(AppointmentStatus::Pending->value,    '10:00:00');
        $this->makeAppointment(AppointmentStatus::Cancelled->value,  '11:00:00');
        $this->makeAppointment(AppointmentStatus::Completed->value,  '12:00:00');

        // Request only cancelled
        $response = $this->getJson("/api/my-company/appointments?date={$this->today}&status=cancelled");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function testMultipleStatusesInFilter(): void
    {
        Sanctum::actingAs($this->owner);

        $this->makeAppointment(AppointmentStatus::Confirmed->value, '09:00:00');
        $this->makeAppointment(AppointmentStatus::Cancelled->value, '10:00:00');
        $this->makeAppointment(AppointmentStatus::Pending->value,   '11:00:00');

        $response = $this->getJson("/api/my-company/appointments?date={$this->today}&status=confirmed,cancelled");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $statuses = array_column($response->json('data'), 'status');
        $this->assertContains('confirmed', $statuses);
        $this->assertContains('cancelled', $statuses);
        $this->assertNotContains('pending', $statuses);
    }

    public function testInvalidStatusValueReturns422(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/my-company/appointments?date={$this->today}&status=invalid_status")
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Only appointments for this company are returned
    // -------------------------------------------------------------------------

    public function testOnlyOwnCompanyAppointmentsAreReturned(): void
    {
        Sanctum::actingAs($this->owner);

        // Appointment for a different company
        $otherCompany = Company::create([
            'name'         => 'Other Salon',
            'address'      => '2 Rue Autre',
            'city'         => 'Lyon',
            'booking_mode' => BookingMode::CapacityBased->value,
        ]);
        $otherService = Service::create([
            'company_id' => $otherCompany->id,
            'name'       => 'Barbe',
            'price'      => 10.00,
            'duration'   => 20,
            'is_active'  => true,
        ]);
        $otherClient = User::factory()->create();
        Appointment::create([
            'user_id'    => $otherClient->id,
            'company_id' => $otherCompany->id,
            'service_id' => $otherService->id,
            'date'       => $this->today,
            'start_time' => '09:00:00',
            'end_time'   => '09:20:00',
            'status'     => AppointmentStatus::Confirmed->value,
        ]);

        // Appointment for this company
        $this->makeAppointment(AppointmentStatus::Confirmed->value, '10:00:00');

        $response = $this->getJson("/api/my-company/appointments?date={$this->today}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // Date-range mode
    // -------------------------------------------------------------------------

    public function testRangeReturnsAppointmentsSortedByDateThenStartTime(): void
    {
        Sanctum::actingAs($this->owner);

        $day1 = Carbon::today()->format('Y-m-d');
        $day2 = Carbon::today()->addDay()->format('Y-m-d');
        $day3 = Carbon::today()->addDays(2)->format('Y-m-d');

        $client = User::factory()->create();

        // Day 2 — later slot (should be 3rd overall)
        $appt3 = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $day2,
            'start_time' => '14:00:00',
            'end_time'   => '14:30:00',
            'status'     => AppointmentStatus::Confirmed->value,
        ]);

        // Day 1 — earlier slot (should be 1st)
        $appt1 = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $day1,
            'start_time' => '09:00:00',
            'end_time'   => '09:30:00',
            'status'     => AppointmentStatus::Confirmed->value,
        ]);

        // Day 2 — earlier slot (should be 2nd)
        $appt2 = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $day2,
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed->value,
        ]);

        $response = $this->getJson("/api/my-company/appointments?start={$day1}&end={$day3}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', (string) $appt1->id)
            ->assertJsonPath('data.1.id', (string) $appt2->id)
            ->assertJsonPath('data.2.id', (string) $appt3->id);
    }

    public function testRangeExceeding42DaysReturns422(): void
    {
        Sanctum::actingAs($this->owner);

        $start = Carbon::today()->format('Y-m-d');
        $end   = Carbon::today()->addDays(43)->format('Y-m-d');

        $this->getJson("/api/my-company/appointments?start={$start}&end={$end}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end']);
    }

    public function testMissingBothDateAndRangeReturns422WithDateErrorKey(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/my-company/appointments')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }
}
