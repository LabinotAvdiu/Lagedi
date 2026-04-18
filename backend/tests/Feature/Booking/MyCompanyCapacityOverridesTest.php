<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyCapacityOverride;
use App\Models\CompanyUser;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyCompanyCapacityOverridesTest extends TestCase
{
    private Company $company;
    private User $owner;

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
    }

    public function testOwnerCanCreateCapacityOverride(): void
    {
        Sanctum::actingAs($this->owner);

        $date = Carbon::today()->addDay()->format('Y-m-d');

        $this->postJson('/api/my-company/capacity-overrides', [
            'date'     => $date,
            'capacity' => 5,
            'notes'    => 'Special event',
        ])->assertStatus(201)
            ->assertJsonPath('data.capacity', 5)
            ->assertJsonPath('data.date', $date);

        $this->assertDatabaseHas('company_capacity_overrides', [
            'company_id' => $this->company->id,
            'date'       => $date,
            'capacity'   => 5,
        ]);
    }

    public function testDuplicateDateReturns422(): void
    {
        Sanctum::actingAs($this->owner);

        $date = Carbon::today()->addDay()->format('Y-m-d');

        CompanyCapacityOverride::create([
            'company_id' => $this->company->id,
            'date'       => $date,
            'capacity'   => 3,
        ]);

        $this->postJson('/api/my-company/capacity-overrides', [
            'date'     => $date,
            'capacity' => 5,
        ])->assertStatus(422);
    }

    public function testOwnerCanListOverrides(): void
    {
        Sanctum::actingAs($this->owner);

        CompanyCapacityOverride::create([
            'company_id' => $this->company->id,
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'capacity'   => 4,
        ]);

        $this->getJson('/api/my-company/capacity-overrides')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function testOwnerCanUpdateOverride(): void
    {
        Sanctum::actingAs($this->owner);

        $override = CompanyCapacityOverride::create([
            'company_id' => $this->company->id,
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'capacity'   => 3,
        ]);

        $this->putJson("/api/my-company/capacity-overrides/{$override->id}", [
            'capacity' => 10,
        ])->assertStatus(200)
            ->assertJsonPath('data.capacity', 10);
    }

    public function testOwnerCanDeleteOverride(): void
    {
        Sanctum::actingAs($this->owner);

        $override = CompanyCapacityOverride::create([
            'company_id' => $this->company->id,
            'date'       => Carbon::today()->addDay()->format('Y-m-d'),
            'capacity'   => 3,
        ]);

        $this->deleteJson("/api/my-company/capacity-overrides/{$override->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('company_capacity_overrides', ['id' => $override->id]);
    }

    public function testCapacityMustBePositive(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/my-company/capacity-overrides', [
            'date'     => Carbon::today()->addDay()->format('Y-m-d'),
            'capacity' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['capacity']);
    }
}
