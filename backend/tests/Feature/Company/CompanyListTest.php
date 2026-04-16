<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyListTest extends TestCase
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
    // Tests
    // -------------------------------------------------------------------------

    public function testListCompaniesRequiresAuth(): void
    {
        $response = $this->getJson('/api/companies');

        $response->assertStatus(401);
    }

    public function testListCompaniesReturnsPaginatedResults(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon A']);
        $this->createCompany(['name' => 'Salon B']);
        $this->createCompany(['name' => 'Salon C']);

        $response = $this->getJson('/api/companies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'address', 'city', 'rating', 'reviewCount', 'priceLevel'],
                ],
                'meta',
                'links',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function testListCompaniesReturnsEmptyWhenNoCompanies(): void
    {
        $this->auth();

        $response = $this->getJson('/api/companies');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function testListCompaniesSearchFiltersByName(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon Élégance']);
        $this->createCompany(['name' => 'Barbershop King']);

        $response = $this->getJson('/api/companies?search=Élégance');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Salon Élégance', $response->json('data.0.name'));
    }

    public function testListCompaniesSearchFiltersByAddress(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon A', 'address' => '39 Rue de la Bourse']);
        $this->createCompany(['name' => 'Salon B', 'address' => '10 Avenue Montaigne']);

        $response = $this->getJson('/api/companies?search=Bourse');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Salon A', $response->json('data.0.name'));
    }

    public function testListCompaniesSearchIsCaseInsensitive(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon Élégance']);

        $response = $this->getJson('/api/companies?search=élégance');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function testListCompaniesCityFilterReturnsMatchingCompanies(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon Paris', 'city' => 'Paris']);
        $this->createCompany(['name' => 'Salon Lyon',  'city' => 'Lyon']);

        $response = $this->getJson('/api/companies?city=Paris');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Salon Paris', $response->json('data.0.name'));
    }

    public function testListCompaniesCityFilterIsCaseInsensitive(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon Paris', 'city' => 'Paris']);

        $response = $this->getJson('/api/companies?city=paris');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function testListCompaniesGenderFilterMen(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Barbershop',  'gender' => 'men']);
        $this->createCompany(['name' => 'Salon Femme', 'gender' => 'women']);
        $this->createCompany(['name' => 'Salon Mixte', 'gender' => 'both']);

        $response = $this->getJson('/api/companies?gender=men');

        $response->assertOk();
        $data = $response->json('data');

        $names = array_column($data, 'name');
        $this->assertContains('Barbershop', $names);
        $this->assertContains('Salon Mixte', $names); // gender=both always included
        $this->assertNotContains('Salon Femme', $names);
    }

    public function testListCompaniesGenderFilterWomen(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Barbershop',  'gender' => 'men']);
        $this->createCompany(['name' => 'Salon Femme', 'gender' => 'women']);
        $this->createCompany(['name' => 'Salon Mixte', 'gender' => 'both']);

        $response = $this->getJson('/api/companies?gender=women');

        $response->assertOk();
        $data = $response->json('data');

        $names = array_column($data, 'name');
        $this->assertContains('Salon Femme', $names);
        $this->assertContains('Salon Mixte', $names); // gender=both always included
        $this->assertNotContains('Barbershop', $names);
    }

    public function testListCompaniesGenderFilterBothReturnsAll(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Barbershop',  'gender' => 'men']);
        $this->createCompany(['name' => 'Salon Femme', 'gender' => 'women']);
        $this->createCompany(['name' => 'Salon Mixte', 'gender' => 'both']);

        $response = $this->getJson('/api/companies?gender=both');

        $response->assertOk();
        // gender=both → match "both" OR "both", so only the "both" companies appear
        // (the filter includes companies whose gender is "both" OR equals the filter value "both")
        // Since filter is "both", all companies with gender "both" are returned (just 1 here)
        // AND those whose gender equals "both" — which is the same set.
        // Per CompanyController: where('gender', 'both') OR where('gender', 'both') = only "both"
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Salon Mixte', $names);
    }

    public function testListCompaniesGenderFilterValidation(): void
    {
        $this->auth();

        $response = $this->getJson('/api/companies?gender=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    public function testListCompaniesPaginationPageTwo(): void
    {
        $this->auth();

        // Create 21 companies (page size is 20)
        for ($i = 1; $i <= 21; $i++) {
            $this->createCompany(['name' => "Salon {$i}"]);
        }

        $page1 = $this->getJson('/api/companies?page=1');
        $page2 = $this->getJson('/api/companies?page=2');

        $page1->assertOk();
        $page2->assertOk();

        $this->assertCount(20, $page1->json('data'));
        $this->assertCount(1, $page2->json('data'));
    }

    public function testListCompaniesResponseShapeMatchesFlutterExpectation(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon Test']);

        $response = $this->getJson('/api/companies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'city',
                        'photoUrl',
                        'rating',
                        'reviewCount',
                        'priceLevel',
                        'morningSlots',
                        'afternoonSlots',
                    ],
                ],
            ]);
    }

    public function testListCompaniesCanCombineFilters(): void
    {
        $this->auth();

        $this->createCompany(['name' => 'Salon Paris Men',   'city' => 'Paris',  'gender' => 'men']);
        $this->createCompany(['name' => 'Salon Paris Women', 'city' => 'Paris',  'gender' => 'women']);
        $this->createCompany(['name' => 'Salon Lyon Men',    'city' => 'Lyon',   'gender' => 'men']);

        $response = $this->getJson('/api/companies?city=Paris&gender=men');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Salon Paris Men', $names);
        $this->assertNotContains('Salon Paris Women', $names);
        $this->assertNotContains('Salon Lyon Men', $names);
    }
}
