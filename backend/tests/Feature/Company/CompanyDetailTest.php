<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyDetailTest extends TestCase
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

    private function auth(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    // -------------------------------------------------------------------------
    // GET /api/companies/{id}
    // -------------------------------------------------------------------------

    public function testShowCompanyRequiresAuth(): void
    {
        $company = $this->createCompany();

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertStatus(401);
    }

    public function testShowCompanyReturnsFullDetail(): void
    {
        $this->auth();
        $company = $this->createCompany(['name' => 'Salon Élégance', 'city' => 'Paris']);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'address', 'city',
                    'rating', 'reviewCount', 'priceLevel',
                    'photos', 'categories', 'employees', 'openingHours',
                ],
            ])
            ->assertJsonPath('data.id', (string) $company->id)
            ->assertJsonPath('data.name', 'Salon Élégance')
            ->assertJsonPath('data.city', 'Paris');
    }

    public function testShowCompanyReturns404ForUnknownId(): void
    {
        $this->auth();

        $response = $this->getJson('/api/companies/99999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function testShowCompanyIncludesOpeningHours(): void
    {
        $this->auth();
        $company = $this->createCompany();

        CompanyOpeningHour::create([
            'company_id'  => $company->id,
            'day_of_week' => 0, // Monday
            'open_time'   => '09:00:00',
            'close_time'  => '18:00:00',
            'is_closed'   => false,
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk();
        $openingHours = $response->json('data.openingHours');
        $this->assertIsArray($openingHours);
        $this->assertNotEmpty($openingHours);
        $this->assertEquals(0, $openingHours[0]['dayOfWeek']);
        $this->assertEquals('09:00:00', $openingHours[0]['openTime']);
        $this->assertFalse($openingHours[0]['isClosed']);
    }

    public function testShowCompanyIncludesActiveEmployees(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $employeeUser = User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Curie']);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $employeeUser->id,
            'role'       => 'employee',
            'is_active'  => true,
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk();
        $employees = $response->json('data.employees');
        $this->assertNotEmpty($employees);
        $this->assertEquals('Marie Curie', $employees[0]['name']);
    }

    public function testShowCompanyExcludesInactiveEmployees(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $employeeUser = User::factory()->create(['first_name' => 'Inactive', 'last_name' => 'Employee']);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $employeeUser->id,
            'role'       => 'employee',
            'is_active'  => false,
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk();
        $this->assertEmpty($response->json('data.employees'));
    }

    public function testShowCompanyIncludesServiceCategories(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $category = ServiceCategory::create([
            'company_id' => $company->id,
            'name'       => 'Coupe',
        ]);

        Service::create([
            'company_id'  => $company->id,
            'category_id' => $category->id,
            'name'        => 'Coupe homme',
            'price'       => 25.00,
            'duration'    => 30,
            'is_active'   => true,
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk();
        $categories = $response->json('data.categories');
        $this->assertNotEmpty($categories);
        $this->assertEquals('Coupe', $categories[0]['name']);
        $this->assertNotEmpty($categories[0]['services']);
        $this->assertEquals('Coupe homme', $categories[0]['services'][0]['name']);
    }

    public function testShowCompanyHidesInactiveServices(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $category = ServiceCategory::create([
            'company_id' => $company->id,
            'name'       => 'Coupe',
        ]);

        Service::create([
            'company_id'  => $company->id,
            'category_id' => $category->id,
            'name'        => 'Service inactif',
            'price'       => 25.00,
            'duration'    => 30,
            'is_active'   => false,
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk();
        // Category with no active services is filtered out
        $this->assertEmpty($response->json('data.categories'));
    }

    // -------------------------------------------------------------------------
    // GET /api/companies/{id}/employees
    // -------------------------------------------------------------------------

    public function testEmployeesRequiresAuth(): void
    {
        $company = $this->createCompany();

        $response = $this->getJson("/api/companies/{$company->id}/employees");

        $response->assertStatus(401);
    }

    public function testEmployeesReturnsActiveEmployees(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $user1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);
        $user2 = User::factory()->create(['first_name' => 'Bob',   'last_name' => 'Dupont']);

        CompanyUser::create(['company_id' => $company->id, 'user_id' => $user1->id, 'role' => 'employee', 'is_active' => true]);
        CompanyUser::create(['company_id' => $company->id, 'user_id' => $user2->id, 'role' => 'employee', 'is_active' => true]);

        $response = $this->getJson("/api/companies/{$company->id}/employees");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'photoUrl', 'specialties', 'role'],
                ],
            ]);

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Alice Martin', $names);
        $this->assertContains('Bob Dupont', $names);
    }

    public function testEmployeesExcludesInactiveEmployees(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $activeUser   = User::factory()->create(['first_name' => 'Active',   'last_name' => 'One']);
        $inactiveUser = User::factory()->create(['first_name' => 'Inactive', 'last_name' => 'Two']);

        CompanyUser::create(['company_id' => $company->id, 'user_id' => $activeUser->id,   'role' => 'employee', 'is_active' => true]);
        CompanyUser::create(['company_id' => $company->id, 'user_id' => $inactiveUser->id, 'role' => 'employee', 'is_active' => false]);

        $response = $this->getJson("/api/companies/{$company->id}/employees");

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Active One', $names);
        $this->assertNotContains('Inactive Two', $names);
    }

    public function testEmployeesReturns404ForUnknownCompany(): void
    {
        $this->auth();

        $response = $this->getJson('/api/companies/99999/employees');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function testEmployeesReturnsEmptyForCompanyWithNoStaff(): void
    {
        $this->auth();
        $company = $this->createCompany();

        $response = $this->getJson("/api/companies/{$company->id}/employees");

        $response->assertOk()
            ->assertJsonPath('data', []);
    }
}
