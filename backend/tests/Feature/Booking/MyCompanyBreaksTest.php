<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyBreak;
use App\Models\CompanyUser;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyCompanyBreaksTest extends TestCase
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

    public function testOwnerCanCreateBreak(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/my-company/breaks', [
            'start_time' => '12:00',
            'end_time'   => '13:00',
            'label'      => 'Lunch',
        ])->assertStatus(201);

        $this->assertDatabaseHas('company_breaks', [
            'company_id' => $this->company->id,
        ]);
    }

    public function testOwnerCanListBreaks(): void
    {
        Sanctum::actingAs($this->owner);

        CompanyBreak::create([
            'company_id'  => $this->company->id,
            'start_time'  => '12:00:00',
            'end_time'    => '13:00:00',
        ]);

        $this->getJson('/api/my-company/breaks')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function testOwnerCanUpdateBreak(): void
    {
        Sanctum::actingAs($this->owner);

        $break = CompanyBreak::create([
            'company_id' => $this->company->id,
            'start_time' => '12:00:00',
            'end_time'   => '13:00:00',
        ]);

        $this->putJson("/api/my-company/breaks/{$break->id}", [
            'label' => 'Updated Label',
        ])->assertStatus(200);

        $this->assertDatabaseHas('company_breaks', [
            'id'    => $break->id,
            'label' => 'Updated Label',
        ]);
    }

    public function testOwnerCanDeleteBreak(): void
    {
        Sanctum::actingAs($this->owner);

        $break = CompanyBreak::create([
            'company_id' => $this->company->id,
            'start_time' => '12:00:00',
            'end_time'   => '13:00:00',
        ]);

        $this->deleteJson("/api/my-company/breaks/{$break->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('company_breaks', ['id' => $break->id]);
    }

    public function testStartTimeMustBeBeforeEndTime(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/my-company/breaks', [
            'start_time' => '14:00',
            'end_time'   => '12:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    public function testDayOfWeekMustBeZeroToSix(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/my-company/breaks', [
            'day_of_week' => 7,
            'start_time'  => '12:00',
            'end_time'    => '13:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['day_of_week']);
    }
}
