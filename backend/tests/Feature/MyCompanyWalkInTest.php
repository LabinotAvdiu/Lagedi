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
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyCompanyWalkInTest extends TestCase
{
    private Company $company;
    private User $owner;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'company']);

        $this->company = Company::create([
            'name'         => 'Test Capacity Salon',
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
            'price'          => 25.00,
            'duration'       => 30,
            'is_active'      => true,
            'max_concurrent' => 2,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'service_id' => $this->service->id,
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
            'phone'      => '0601020304',
        ], $overrides);
    }

    public function testCapacityBasedOwnerCreatesWalkIn(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/my-company/walk-in', $this->validPayload())
            ->assertStatus(201);

        $response->assertJsonPath('data.status', 'confirmed')
                 ->assertJsonPath('data.isWalkIn', true);

        $this->assertDatabaseHas('appointments', [
            'company_id'      => $this->company->id,
            'company_user_id' => null,
            'service_id'      => $this->service->id,
            'status'          => AppointmentStatus::Confirmed->value,
            'is_walk_in'      => 1,
            'walk_in_first_name' => 'Jean',
        ]);
    }

    public function testEmployeeBasedOwnerIsRejectedWith403(): void
    {
        $this->company->update(['booking_mode' => BookingMode::EmployeeBased->value]);

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/my-company/walk-in', $this->validPayload())
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function testNonOwnerIsRejectedWith403(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->postJson('/api/my-company/walk-in', $this->validPayload())
            ->assertStatus(403);
    }

    public function testOwnerCanOverbookWalkIn(): void
    {
        // Owner-created walk-ins bypass capacity limits by design — the owner
        // manages their own schedule and may overbook intentionally.
        Sanctum::actingAs($this->owner);

        $date = Carbon::today()->addDay()->format('Y-m-d');

        foreach (range(1, 2) as $i) {
            Appointment::create([
                'company_id'      => $this->company->id,
                'company_user_id' => null,
                'service_id'      => $this->service->id,
                'user_id'         => null,
                'date'            => $date,
                'start_time'      => '10:00:00',
                'end_time'        => '10:30:00',
                'status'          => AppointmentStatus::Confirmed,
                'is_walk_in'      => true,
                'walk_in_first_name' => "Client $i",
            ]);
        }

        $this->postJson('/api/my-company/walk-in', $this->validPayload(['date' => $date]))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function testServiceFromAnotherCompanyIsRejectedWith422(): void
    {
        $otherCompany = Company::create([
            'name'         => 'Other Salon',
            'address'      => '2 Rue Test',
            'city'         => 'Lyon',
            'booking_mode' => BookingMode::CapacityBased->value,
        ]);

        $otherService = Service::create([
            'company_id' => $otherCompany->id,
            'name'       => 'Coloration',
            'price'      => 50.00,
            'duration'   => 60,
            'is_active'  => true,
        ]);

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/my-company/walk-in', $this->validPayload(['service_id' => $otherService->id]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function testMissingFirstNameReturns422(): void
    {
        Sanctum::actingAs($this->owner);

        $payload = $this->validPayload();
        unset($payload['first_name']);

        $this->postJson('/api/my-company/walk-in', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function testAvailabilityCacheIsInvalidatedAfterWalkIn(): void
    {
        Sanctum::actingAs($this->owner);

        $date    = Carbon::today()->addDay()->format('Y-m-d');
        $cacheKey = "company:availability:{$this->company->id}:{$date}";

        Cache::put($cacheKey, 'stub', 60);
        $this->assertTrue(Cache::has($cacheKey));

        $this->postJson('/api/my-company/walk-in', $this->validPayload(['date' => $date]))
            ->assertStatus(201);

        $this->assertFalse(Cache::has($cacheKey));
    }
}
