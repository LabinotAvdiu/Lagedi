<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * POST /api/companies/{company}/favorite
     *
     * Marks {company} as a favorite for the authenticated user.
     * Idempotent — calling it twice does not create a duplicate row
     * (the composite PK constraint + firstOrCreate guarantee this).
     *
     * Returns 204 No Content on success.
     */
    public function store(Company $company): Response
    {
        $userId = Auth::id();

        // INSERT IGNORE relies on the composite PK constraint to silently
        // discard duplicates at the DB level — true idempotence with a single
        // round-trip and no race condition.
        DB::table('company_favorites')->insertOrIgnore([
            'user_id'    => $userId,
            'company_id' => $company->id,
            'created_at' => now(),
        ]);

        // Invalidate the per-user home cache so the next GET /api/companies
        // reflects the updated isFavorite flags and favorites-first ordering.
        $this->invalidateUserHomeCache($userId);

        return response()->noContent();
    }

    /**
     * DELETE /api/companies/{company}/favorite
     *
     * Removes {company} from the authenticated user's favorites.
     * Idempotent — no error if the favorite did not exist.
     *
     * Returns 204 No Content on success.
     */
    public function destroy(Company $company): Response
    {
        $userId = Auth::id();

        // DELETE is a no-op when the row is absent — true idempotence.
        DB::table('company_favorites')
            ->where('user_id', $userId)
            ->where('company_id', $company->id)
            ->delete();

        $this->invalidateUserHomeCache($userId);

        return response()->noContent();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Invalidates ALL paginated home-listing cache entries for this user.
     *
     * The home cache key pattern is: companies:list:{userId}:{md5(filters)}.
     * Because we cannot enumerate all pages/filter combinations cheaply,
     * we use a per-user version key: incrementing it makes every old key stale
     * without needing to track or delete individual keys.
     *
     * See CompanyController::index() for the matching read-side logic.
     */
    private function invalidateUserHomeCache(int $userId): void
    {
        Cache::forget("companies:list:user_version:{$userId}");
    }
}
