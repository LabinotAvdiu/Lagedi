<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoShowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $owner;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

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
            'company_id' => $this->company->id,
            'name'       => 'Coupe',
            'price'      => 25.0,
            'duration'   => 30,
            'is_active'  => true,
        ]);
    }

    /**
     * Crée un appointment confirmed dans le passé (il y a N heures).
     */
    private function makePastConfirmedAppointment(int $hoursAgo = 2): Appointment
    {
        $client = User::factory()->create();

        return Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => now()->subHours($hoursAgo)->format('Y-m-d'),
            'start_time' => now()->subHours($hoursAgo)->format('H:i:s'),
            'end_time'   => now()->subHours($hoursAgo - 1)->format('H:i:s'),
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);
    }

    // =========================================================================
    // Test 1 — Owner peut marquer un appointment confirmé passé comme no_show
    // =========================================================================

    public function test_owner_can_mark_past_confirmed_appointment_as_no_show(): void
    {
        $appointment = $this->makePastConfirmedAppointment();

        Sanctum::actingAs($this->owner);

        $response = $this->putJson(
            "/api/my-company/appointments/{$appointment->id}/status",
            ['status' => 'no_show']
        );

        $response->assertOk()
            ->assertJsonPath('status', 'no_show');

        $this->assertDatabaseHas('appointments', [
            'id'     => $appointment->id,
            'status' => 'no_show',
        ]);
    }

    // =========================================================================
    // Test 2 — Rejet si RDV futur
    // =========================================================================

    public function test_no_show_rejected_for_future_appointment(): void
    {
        $client = User::factory()->create();

        $future = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => now()->addHours(3)->format('Y-m-d'),
            'start_time' => now()->addHours(3)->format('H:i:s'),
            'end_time'   => now()->addHours(4)->format('H:i:s'),
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ]);

        Sanctum::actingAs($this->owner);

        $this->putJson(
            "/api/my-company/appointments/{$future->id}/status",
            ['status' => 'no_show']
        )->assertStatus(422)
         ->assertJsonPath('errors.status.0', 'appointment-not-started-yet');
    }

    // =========================================================================
    // Test 3 — Rejet si status initial != confirmed (ex: pending)
    // =========================================================================

    public function test_no_show_rejected_if_status_is_pending(): void
    {
        $client = User::factory()->create();

        $pending = Appointment::create([
            'user_id'    => $client->id,
            'company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'date'       => now()->subHours(2)->format('Y-m-d'),
            'start_time' => now()->subHours(2)->format('H:i:s'),
            'end_time'   => now()->subHours(1)->format('H:i:s'),
            'status'     => AppointmentStatus::Pending,
            'is_walk_in' => false,
        ]);

        Sanctum::actingAs($this->owner);

        $this->putJson(
            "/api/my-company/appointments/{$pending->id}/status",
            ['status' => 'no_show']
        )->assertStatus(422)
         ->assertJsonFragment(['message' => 'This status transition is not allowed.']);
    }
}
