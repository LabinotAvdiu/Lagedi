<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapacityBookingTest extends TestCase
{
    private Company $company;
    private Service $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'         => 'Capacity Salon',
            'address'      => '1 Rue Test',
            'city'         => 'Paris',
            'booking_mode' => BookingMode::CapacityBased->value,
        ]);

        $this->service = Service::create([
            'company_id'     => $this->company->id,
            'name'           => 'Coupe',
            'price'          => 30.00,
            'duration'       => 30,
            'is_active'      => true,
            'max_concurrent' => 2,
        ]);

        $this->user = User::factory()->create();
    }

    private function futureDateTime(int $daysAhead = 1, string $time = '10:00:00'): string
    {
        return Carbon::today()->addDays($daysAhead)->format('Y-m-d') . 'T' . $time;
    }

    public function testCapacityBasedBookingCreatesPendingAppointment(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $this->futureDateTime(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'status'     => AppointmentStatus::Pending->value,
        ]);
    }

    public function testCapacityBookingReturns409WhenFull(): void
    {
        Sanctum::actingAs($this->user);

        $dateTime = $this->futureDateTime();
        $date     = Carbon::today()->addDay()->format('Y-m-d');

        // Fill capacity (2 slots)
        for ($i = 0; $i < 2; $i++) {
            $u = User::factory()->create();
            Appointment::create([
                'user_id'    => $u->id,
                'company_id' => $this->company->id,
                'service_id' => $this->service->id,
                'date'       => $date,
                'start_time' => '10:00:00',
                'end_time'   => '10:30:00',
                'status'     => AppointmentStatus::Pending,
            ]);
        }

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $dateTime,
        ]);

        $response->assertStatus(409);
    }

    public function testRejectedAppointmentKeepsSlotOccupied(): void
    {
        Sanctum::actingAs($this->user);

        $date = Carbon::today()->addDay()->format('Y-m-d');

        // 1 rejected + 1 pending = fills capacity of 2
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Appointment::create([
            'user_id'    => $u1->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $date,
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Rejected,
        ]);
        Appointment::create([
            'user_id'    => $u2->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $date,
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Pending,
        ]);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $date . 'T10:00:00',
        ]);

        $response->assertStatus(409);
    }

    public function testCancelledAppointmentFreesCapacity(): void
    {
        Sanctum::actingAs($this->user);

        $date = Carbon::today()->addDay()->format('Y-m-d');

        // 1 cancelled (does NOT count) + 1 pending = only 1 out of 2 used
        $u1 = User::factory()->create();
        Appointment::create([
            'user_id'    => $u1->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => $date,
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Cancelled,
        ]);

        $response = $this->postJson('/api/bookings', [
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date_time'  => $date . 'T10:00:00',
        ]);

        $response->assertStatus(201);
    }
}
