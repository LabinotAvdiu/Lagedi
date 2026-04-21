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

class AppointmentApprovalTest extends TestCase
{
    private Company $company;
    private User $owner;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner   = User::factory()->create(['role' => 'company']);
        $this->company = Company::create([
            'name'         => 'Capacity Salon',
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
            'name'           => 'Coupe',
            'price'          => 30.00,
            'duration'       => 30,
            'is_active'      => true,
            'max_concurrent' => 3,
        ]);
    }

    private function createPendingAppointment(): Appointment
    {
        $client = User::factory()->create();
        return Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Pending,
        ]);
    }

    public function testOwnerCanConfirmPendingAppointment(): void
    {
        Sanctum::actingAs($this->owner);

        $appointment = $this->createPendingAppointment();

        $this->putJson("/api/my-company/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ])->assertStatus(200)
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'id'     => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);
    }

    public function testOwnerCanRejectPendingAppointment(): void
    {
        Sanctum::actingAs($this->owner);

        $appointment = $this->createPendingAppointment();

        $this->putJson("/api/my-company/appointments/{$appointment->id}/status", [
            'status' => 'rejected',
        ])->assertStatus(200)
            ->assertJsonPath('status', 'rejected');
    }

    public function testNonOwnerCannotApproveAppointment(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $appointment = $this->createPendingAppointment();

        $this->putJson("/api/my-company/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ])->assertStatus(403);
    }

    public function testCannotApproveAlreadyCancelledAppointment(): void
    {
        Sanctum::actingAs($this->owner);

        $client = User::factory()->create();
        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Cancelled,
        ]);

        $this->putJson("/api/my-company/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ])->assertStatus(422);
    }

    public function testOwnerCanApproveInEmployeeBasedMode(): void
    {
        // The approval flow is now unified — owners can confirm/reject
        // pending bookings regardless of the company's booking mode. The
        // transition matrix (pending → confirmed|rejected|cancelled) is the
        // single source of truth for what's reachable.
        Sanctum::actingAs($this->owner);
        $this->company->update(['booking_mode' => BookingMode::EmployeeBased->value]);

        $appointment = $this->createPendingAppointment();

        $this->putJson("/api/my-company/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ])->assertStatus(200);

        $this->assertEquals('confirmed', $appointment->fresh()->status->value);
    }

    public function testOwnerCanListPendingAppointments(): void
    {
        Sanctum::actingAs($this->owner);

        $this->createPendingAppointment();
        $this->createPendingAppointment();

        $this->getJson('/api/my-company/appointments/pending')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
