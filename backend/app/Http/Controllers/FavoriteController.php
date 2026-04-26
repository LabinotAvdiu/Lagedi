<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * POST /api/companies/{company}/favorite
     * Body (optional): { "employee_id": 18 }
     *
     * Marks {company} as a favorite for the authenticated user. When
     * `employee_id` is provided AND the company is in `employee_based` mode
     * AND the employee belongs to the company, we persist the preference
     * — the client app will then show a "Booking with this pro" version
     * of the favorite (heart filled, employee badge) alongside a plain
     * version of the same salon (free employee selection).
     *
     * Idempotent — calling it twice with the same body upserts cleanly.
     * Calling with a different `employee_id` updates the preference.
     *
     * Returns 204 No Content on success.
     */
    public function store(Request $request, Company $company): Response
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'employee_id' => 'nullable|integer|exists:users,id',
        ]);

        $employeeId = $this->resolvePreferredEmployeeId($company, $validated['employee_id'] ?? null);

        // upsert — composite PK on (user_id, company_id) means we need
        // INSERT...ON DUPLICATE KEY UPDATE to handle re-fav with new employee.
        DB::table('company_favorites')->upsert(
            [[
                'user_id'               => $userId,
                'company_id'            => $company->id,
                'preferred_employee_id' => $employeeId,
                'created_at'            => now(),
            ]],
            ['user_id', 'company_id'],
            ['preferred_employee_id'],
        );

        $this->invalidateUserHomeCache($userId);

        return response()->noContent();
    }

    /**
     * DELETE /api/companies/{company}/favorite
     *
     * Removes {company} from the authenticated user's favorites — including
     * any preferred_employee_id preference. Idempotent.
     *
     * Returns 204 No Content on success.
     */
    public function destroy(Company $company): Response
    {
        $userId = Auth::id();

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
     * Validate that the given employee_id is allowed for this company:
     *  - company must be in `employee_based` booking mode
     *  - employee must be in the company's company_user pivot with role=employee
     *
     * Returns null if the input is null OR if validation fails — silently
     * downgrading to a generic favorite rather than rejecting the request,
     * so the favorite still gets saved if the employee was deleted between
     * the time the QR was generated and the scan.
     */
    private function resolvePreferredEmployeeId(Company $company, ?int $employeeId): ?int
    {
        if ($employeeId === null) {
            return null;
        }

        $bookingMode = $company->booking_mode instanceof \BackedEnum
            ? $company->booking_mode->value
            : (string) $company->booking_mode;

        if ($bookingMode !== 'employee_based') {
            return null;
        }

        $belongs = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $employeeId)
            ->where('company_role', 'employee')
            ->exists();

        return $belongs ? $employeeId : null;
    }

    /**
     * Invalidates ALL paginated home-listing cache entries for this user.
     * See CompanyController::index() for the matching read-side logic.
     */
    private function invalidateUserHomeCache(int $userId): void
    {
        Cache::forget("companies:list:user_version:{$userId}");
    }
}
