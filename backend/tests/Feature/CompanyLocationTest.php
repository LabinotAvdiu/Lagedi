<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyLocationTest extends TestCase
{
    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'   => 'Jean',
            'last_name'    => 'Dupont',
            'email'        => 'jean@example.com',
            'password'     => 'Password1',
            'role'           => 'company',
            'company_name'   => 'Salon Test',
            'company_gender' => 'both',
            'address'        => '1 Rue de la Paix',
            'city'           => 'Paris',
        ], $overrides);
    }

    public function testSignupWithLatLngSavesPointAndExposesCoords(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->companyPayload([
            'latitude'  => 48.8566,
            'longitude' => 2.3522,
        ]));

        $response->assertStatus(201);

        $company = Company::where('name', 'Salon Test')->first();
        $this->assertNotNull($company);

        $row = DB::selectOne(
            'SELECT ST_X(location) AS lng, ST_Y(location) AS lat FROM companies WHERE id = ?',
            [$company->id]
        );

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(2.3522, (float) $row->lng, 0.0001);
        $this->assertEqualsWithDelta(48.8566, (float) $row->lat, 0.0001);

        $detail = $this->getJson("/api/companies/{$company->id}");
        $detail->assertOk();
        $this->assertEqualsWithDelta(48.8566, $detail->json('data.latitude'), 0.0001);
        $this->assertEqualsWithDelta(2.3522, $detail->json('data.longitude'), 0.0001);
    }

    public function testUpdateCompanyWithLatButNoLngReturns422(): void
    {
        $owner = User::factory()->create(['role' => 'company']);

        $company = Company::create([
            'name'    => 'Salon Test',
            'address' => '1 Rue Test',
            'city'    => 'Paris',
        ]);

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/my-company', [
            'latitude' => 48.8566,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['longitude']);
    }

    public function testUpdateCompanyWithNewLatLngInvalidatesCacheAndReturnsNewValues(): void
    {
        $owner = User::factory()->create(['role' => 'company']);

        $company = Company::create([
            'name'    => 'Salon Test',
            'address' => '1 Rue Test',
            'city'    => 'Paris',
        ]);

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => 'owner',
            'is_active'  => true,
        ]);

        // Prime the cache
        $this->getJson("/api/companies/{$company->id}")->assertOk();

        Sanctum::actingAs($owner);

        $update = $this->putJson('/api/my-company', [
            'latitude'  => 43.2965,
            'longitude' => 5.3698,
        ]);

        $update->assertOk();

        $row = DB::selectOne(
            'SELECT ST_X(location) AS lng, ST_Y(location) AS lat FROM companies WHERE id = ?',
            [$company->id]
        );

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(5.3698, (float) $row->lng, 0.0001);
        $this->assertEqualsWithDelta(43.2965, (float) $row->lat, 0.0001);

        // Cache must have been busted — fresh GET should reflect new coords
        $detail = $this->getJson("/api/companies/{$company->id}");
        $detail->assertOk();
        $this->assertEqualsWithDelta(43.2965, $detail->json('data.latitude'), 0.0001);
        $this->assertEqualsWithDelta(5.3698, $detail->json('data.longitude'), 0.0001);
    }
}
