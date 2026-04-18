<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the company favorites feature.
 *
 * Covered:
 *   1. Authenticated user can add a favorite → 204
 *   2. Adding the same favorite twice is idempotent (no 500, no duplicate row)
 *   3. Authenticated user can remove a favorite → 204
 *   4. Removing a non-existing favorite is idempotent → 204
 *   5. Anonymous request returns 401 on both POST and DELETE
 *   6. GET /api/companies (no filter) — favorites appear first, oldest-added first
 *   7. GET /api/companies?city=Prishtina — favorites from another city are excluded;
 *      favorites that match the city filter are promoted to the top
 */
class FavoriteTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCompany(array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name'    => 'Salon Test',
            'address' => '1 Rruga Test',
            'city'    => 'Prishtina',
            'gender'  => 'both',
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // Test 1 — Authenticated user can add a favorite (204)
    // -------------------------------------------------------------------------

    public function testAuthUserCanAddFavorite(): void
    {
        $user    = User::factory()->create();
        $company = $this->createCompany();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/companies/{$company->id}/favorite");

        $response->assertNoContent();

        // Row must exist in the pivot table.
        $this->assertDatabaseHas('company_favorites', [
            'user_id'    => $user->id,
            'company_id' => $company->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Adding the same favorite twice is idempotent
    // -------------------------------------------------------------------------

    public function testAddFavoriteIsIdempotent(): void
    {
        $user    = User::factory()->create();
        $company = $this->createCompany();

        Sanctum::actingAs($user);

        // First call
        $this->postJson("/api/companies/{$company->id}/favorite")->assertNoContent();
        // Second call — must not crash, must not duplicate the row
        $this->postJson("/api/companies/{$company->id}/favorite")->assertNoContent();

        $count = DB::table('company_favorites')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->count();

        $this->assertSame(1, $count, 'Duplicate favorite rows must not be created.');
    }

    // -------------------------------------------------------------------------
    // Test 3 — Authenticated user can remove a favorite (204)
    // -------------------------------------------------------------------------

    public function testAuthUserCanRemoveFavorite(): void
    {
        $user    = User::factory()->create();
        $company = $this->createCompany();

        // Seed the favorite directly.
        DB::table('company_favorites')->insert([
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/companies/{$company->id}/favorite");

        $response->assertNoContent();

        $this->assertDatabaseMissing('company_favorites', [
            'user_id'    => $user->id,
            'company_id' => $company->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 4 — Removing a non-existing favorite is idempotent (204, no error)
    // -------------------------------------------------------------------------

    public function testRemoveFavoriteIsIdempotent(): void
    {
        $user    = User::factory()->create();
        $company = $this->createCompany();

        Sanctum::actingAs($user);

        // No favorite exists — must still return 204.
        $this->deleteJson("/api/companies/{$company->id}/favorite")->assertNoContent();
        // Second call — still 204.
        $this->deleteJson("/api/companies/{$company->id}/favorite")->assertNoContent();
    }

    // -------------------------------------------------------------------------
    // Test 5 — Anonymous requests get 401 on both verbs
    // -------------------------------------------------------------------------

    public function testAnonymousCannotAddOrRemoveFavorite(): void
    {
        $company = $this->createCompany();

        $this->postJson("/api/companies/{$company->id}/favorite")->assertUnauthorized();
        $this->deleteJson("/api/companies/{$company->id}/favorite")->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Test 6 — GET /api/companies (no filter): favorites in head, oldest first
    //
    // Scenario:
    //   - company A (no filter) — NOT a favorite
    //   - company B (no filter) — favorite, added LAST  (created_at = now+2s)
    //   - company C (no filter) — favorite, added FIRST (created_at = now)
    //
    // Expected order in response: C → B → A
    // -------------------------------------------------------------------------

    public function testHomeListPromotesFavoritesOldestFirst(): void
    {
        $user = User::factory()->create();

        $companyA = $this->createCompany(['name' => 'Alpha Salon']);
        $companyB = $this->createCompany(['name' => 'Beta Salon']);
        $companyC = $this->createCompany(['name' => 'Gamma Salon']);

        // C added first, B added 5 seconds later.
        DB::table('company_favorites')->insert([
            'user_id'    => $user->id,
            'company_id' => $companyC->id,
            'created_at' => now()->subSeconds(5),
        ]);
        DB::table('company_favorites')->insert([
            'user_id'    => $user->id,
            'company_id' => $companyB->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/companies');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        // C and B must appear before A.
        $posA = array_search($companyA->id, $ids, true);
        $posB = array_search($companyB->id, $ids, true);
        $posC = array_search($companyC->id, $ids, true);

        $this->assertNotFalse($posC, 'Company C must be in the response.');
        $this->assertNotFalse($posB, 'Company B must be in the response.');
        $this->assertNotFalse($posA, 'Company A must be in the response.');

        // Oldest favorite (C) must come before newest favorite (B).
        $this->assertLessThan($posB, $posC, 'Oldest favorite (C) must precede newest favorite (B).');

        // Both favorites must precede the non-favorite (A).
        $this->assertLessThan($posA, $posC, 'Favorite C must precede non-favorite A.');
        $this->assertLessThan($posA, $posB, 'Favorite B must precede non-favorite A.');

        // isFavorite flags must be correct.
        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertTrue((bool) $byId[(string) $companyC->id]['isFavorite']);
        $this->assertTrue((bool) $byId[(string) $companyB->id]['isFavorite']);
        $this->assertFalse((bool) $byId[(string) $companyA->id]['isFavorite']);
    }

    // -------------------------------------------------------------------------
    // Test 7 — GET /api/companies?city=Prishtina
    //
    // Scenario:
    //   - company P1 in Prishtina  — favorite
    //   - company P2 in Prishtina  — NOT a favorite
    //   - company G  in Gjakova    — favorite (different city)
    //
    // Expected: G is absent from results (city filter).
    //           P1 appears before P2 (favorite promoted).
    //           P1.isFavorite = true, P2.isFavorite = false.
    // -------------------------------------------------------------------------

    public function testCityFilterExcludesWrongCityFavoritesAndPromotesMatchingOnes(): void
    {
        $user = User::factory()->create();

        $companyP1 = $this->createCompany(['name' => 'Prishtina Fav',    'city' => 'Prishtina']);
        $companyP2 = $this->createCompany(['name' => 'Prishtina NonFav', 'city' => 'Prishtina']);
        $companyG  = $this->createCompany(['name' => 'Gjakova Fav',      'city' => 'Gjakova']);

        // P1 and G are favorites; G is in a different city.
        DB::table('company_favorites')->insert([
            ['user_id' => $user->id, 'company_id' => $companyG->id,  'created_at' => now()->subSeconds(10)],
            ['user_id' => $user->id, 'company_id' => $companyP1->id, 'created_at' => now()],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/companies?city=Prishtina');

        $response->assertOk();

        $responseData = collect($response->json('data'));
        $ids          = $responseData->pluck('id')->map(fn ($id) => (int) $id)->all();

        // G (Gjakova) must NOT appear.
        $this->assertNotContains($companyG->id, $ids, 'Gjakova company must be absent when filtering by Prishtina.');

        // P1 and P2 must both appear.
        $this->assertContains($companyP1->id, $ids, 'P1 (Prishtina, favorite) must appear.');
        $this->assertContains($companyP2->id, $ids, 'P2 (Prishtina, non-favorite) must appear.');

        // P1 (favorite) must be promoted before P2.
        $posP1 = array_search($companyP1->id, $ids, true);
        $posP2 = array_search($companyP2->id, $ids, true);
        $this->assertLessThan($posP2, $posP1, 'Favorite P1 must precede non-favorite P2.');

        // isFavorite flags.
        $byId = $responseData->keyBy('id');
        $this->assertTrue((bool) $byId[(string) $companyP1->id]['isFavorite'], 'P1.isFavorite must be true.');
        $this->assertFalse((bool) $byId[(string) $companyP2->id]['isFavorite'], 'P2.isFavorite must be false.');
    }
}
