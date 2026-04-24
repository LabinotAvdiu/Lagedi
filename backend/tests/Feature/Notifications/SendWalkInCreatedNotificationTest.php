<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\BookingMode;
use App\Enums\CompanyRole;
use App\Jobs\SendWalkInCreatedNotification;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendWalkInCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testWalkInByEmployeeDispatchesJob(): void
    {
        Queue::fake();

        $owner    = User::factory()->create();
        $employee = User::factory()->create();
        $company  = Company::factory()->create(['booking_mode' => BookingMode::EmployeeBased]);

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $empPivot = CompanyUser::create([
            'user_id'    => $employee->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Employee->value,
            'is_active'  => true,
        ]);

        $category = ServiceCategory::create(['company_id' => $company->id, 'name' => 'Coupe']);
        $service  = Service::factory()->create([
            'company_id'  => $company->id,
            'category_id' => $category->id,
            'duration'    => 30,
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/my-company/walk-in', [
            'service_id' => $service->id,
            'date'       => now()->format('Y-m-d'),
            'start_time' => '14:00',
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
        ])->assertStatus(201);

        Queue::assertPushed(SendWalkInCreatedNotification::class);
    }

    public function testWalkInByOwnerDoesNotDispatchJob(): void
    {
        Queue::fake();

        $owner   = User::factory()->create();
        $company = Company::factory()->create(['booking_mode' => BookingMode::EmployeeBased]);

        CompanyUser::create([
            'user_id'    => $owner->id,
            'company_id' => $company->id,
            'role'       => CompanyRole::Owner->value,
            'is_active'  => true,
        ]);

        $category = ServiceCategory::create(['company_id' => $company->id, 'name' => 'Coupe']);
        $service  = Service::factory()->create([
            'company_id'  => $company->id,
            'category_id' => $category->id,
            'duration'    => 30,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/my-company/walk-in', [
            'service_id' => $service->id,
            'date'       => now()->format('Y-m-d'),
            'start_time' => '15:00',
            'first_name' => 'Marie',
        ])->assertStatus(201);

        // L'owner crée lui-même → pas de notification.
        Queue::assertNotPushed(SendWalkInCreatedNotification::class);
    }
}
