<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Enums\DayOfWeek;
use App\Http\Requests\Company\GetAvailabilityRequest;
use App\Http\Requests\Company\GetSlotsRequest;
use App\Http\Requests\Company\ListCompaniesRequest;
use App\Http\Resources\CompanyDetailResource;
use App\Http\Resources\CompanyListResource;
use App\Http\Resources\CompanyResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyBreak;
use App\Models\CompanyCapacityOverride;
use App\Models\CompanyDayOff;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\EmployeeBreak;
use App\Models\EmployeeDayOff;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Columns needed for the listing cards — nothing more.
     * Explicitly selecting prevents SELECT * and avoids fetching heavy columns
     * like `description`, `location` (POINT binary), `email`, `phone`, etc.
     */
    private const LIST_COLUMNS = [
        'id', 'name', 'address', 'city',
        'profile_image_url', 'rating', 'review_count', 'price_level',
        'gender', 'booking_mode',
    ];

    /**
     * GET /api/companies
     *
     * Returns a paginated list of companies (lightweight card view).
     *
     * Cache strategy — per-user:
     *   - Authenticated user  → key includes userId + a per-user version token so
     *     that POST/DELETE /favorite can bust only that user's cache without
     *     touching other users' cached results.
     *   - Anonymous           → key is global (no userId) — shared across guests.
     *
     * isFavorite flag:
     *   - Loaded in ONE extra query (not N+1): we fetch all favorite company IDs
     *     for the current user, then do an O(1) set-lookup per item.
     *
     * Favorites-first ordering:
     *   - When NO filter is active: favorites are moved to the top of the page,
     *     sorted by company_favorites.created_at ASC (oldest-added first), then
     *     the rest in the normal rating DESC / name ASC order.
     *   - When ANY filter is active: favorites that match the filter are still
     *     promoted to the top (created_at ASC), then the rest of the filtered
     *     results. This gives a consistent UX regardless of search context.
     *
     * Query params:
     *   search  — full-text search on name or address (LIKE fallback)
     *   city    — exact city filter (case-insensitive)
     *   gender  — "men" | "women" | "both"
     *   date    — ISO date filter (reserved for slot-availability filtering)
     *   page    — pagination page number
     */
    public function index(ListCompaniesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        // Route is public, so no middleware activates the sanctum guard.
        // Resolve the user explicitly via the sanctum guard — null for guests,
        // the authenticated user when a valid bearer token is present.
        $userId    = Auth::guard('sanctum')->id();

        // ------------------------------------------------------------------
        // Per-user version token: incrementing it busts the cache for this
        // user across all pages/filter combos without touching other users.
        // The version is stored in a dedicated key; we read it here so it
        // participates in the cache key below.
        // ------------------------------------------------------------------
        $userVersion = $userId
            ? (int) Cache::get("companies:list:user_version:{$userId}", 0)
            : 0;

        // Build a deterministic cache key:
        //   - For auth users  → includes userId + version (per-user isolation)
        //   - For guests       → global key (all guests share the same result)
        $cacheKeySuffix = md5(serialize([
            $validated['search'] ?? null,
            $validated['city']   ?? null,
            $validated['gender'] ?? null,
            $validated['date']   ?? null,
            $validated['page']   ?? 1,
        ]));

        $cacheKey = $userId
            ? "companies:list:u{$userId}:v{$userVersion}:{$cacheKeySuffix}"
            : "companies:list:{$cacheKeySuffix}";

        // ------------------------------------------------------------------
        // Fetch and cache the raw company data (NO user-specific data inside
        // the cache — isFavorite is injected AFTER the cache read so the
        // same cached blob can never leak one user's data to another).
        // ------------------------------------------------------------------
        $cached = Cache::remember($cacheKey, 300, function () use ($validated): array {
            $query = Company::select(self::LIST_COLUMNS)
                ->with(['galleryImages' => function ($q) {
                    $q->select('id', 'company_id', 'sort_order',
                            'thumbnail_path', 'image_path')
                      ->orderBy('sort_order');
                }]);

            // --- Search: multi-word LIKE across name / address / city /
            //            employee first_name / last_name ---
            //
            // The query is split on whitespace. Every word must match
            // somewhere (AND between words, OR between fields), so a user
            // who types "sal don" finds "Salon Donjeta" even when the
            // match is split between the company name and an employee name.
            if (! empty($validated['search'])) {
                $search = trim($validated['search']);
                $words  = array_values(array_unique(array_filter(
                    preg_split('/\s+/', $search) ?: [],
                    fn ($w) => $w !== ''
                )));

                if (! empty($words)) {
                    $query->where(function ($outer) use ($words): void {
                        foreach ($words as $word) {
                            $like = '%' . $word . '%';
                            $outer->where(function ($q) use ($like): void {
                                $q->where('name', 'LIKE', $like)
                                  ->orWhere('address', 'LIKE', $like)
                                  ->orWhere('city', 'LIKE', $like)
                                  ->orWhereExists(function ($sub) use ($like): void {
                                      $sub->select(DB::raw(1))
                                          ->from('company_user')
                                          ->join(
                                              'users',
                                              'users.id',
                                              '=',
                                              'company_user.user_id'
                                          )
                                          ->whereColumn(
                                              'company_user.company_id',
                                              'companies.id'
                                          )
                                          ->where(function ($u) use ($like): void {
                                              $u->where('users.first_name', 'LIKE', $like)
                                                ->orWhere('users.last_name', 'LIKE', $like);
                                          });
                                  });
                            });
                        }
                    });
                }
            }

            // --- City filter ---
            if (! empty($validated['city'])) {
                $query->whereRaw('LOWER(city) = ?', [mb_strtolower($validated['city'])]);
            }

            // --- Gender filter ---
            if (! empty($validated['gender'])) {
                $gender = $validated['gender'];
                $query->where(function ($q) use ($gender): void {
                    $q->where('gender', $gender)
                      ->orWhere('gender', 'both');
                });
            }

            $paginator = $query
                ->orderByDesc('rating')
                ->orderBy('name')
                ->paginate(self::PER_PAGE);

            $items      = $paginator->items() ?: [];
            $companyIds = array_map(fn ($c) => $c->id, $items);
            $availability = $this->computeAvailability(
                $companyIds,
                $validated['date'] ?? null,
            );

            $itemArrays = [];
            foreach ($items as $c) {
                $arr                 = $c->toArray();
                $arr['availability'] = $availability[$c->id] ?? [];
                $arr['photo_url']    = $this->resolveListCoverPhoto($c);
                $itemArrays[]        = $arr;
            }

            return [
                'items'        => $itemArrays,
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ];
        });

        // ------------------------------------------------------------------
        // Load user's favorite IDs in ONE query (never inside a loop).
        // For anonymous users this is an empty set — no DB hit.
        // ------------------------------------------------------------------
        $favoriteIds = [];        // company_id → ['created_at', 'preferred_employee_id', 'preferred_employee_name']
        if ($userId) {
            $rows = DB::table('company_favorites as cf')
                ->leftJoin('users', 'users.id', '=', 'cf.preferred_employee_id')
                ->where('cf.user_id', $userId)
                ->get([
                    'cf.company_id',
                    'cf.created_at',
                    'cf.preferred_employee_id',
                    'users.name as preferred_employee_name',
                ]);

            foreach ($rows as $row) {
                $favoriteIds[$row->company_id] = [
                    'created_at'              => $row->created_at,
                    'preferred_employee_id'   => $row->preferred_employee_id,
                    'preferred_employee_name' => $row->preferred_employee_name,
                ];
            }
        }

        // ------------------------------------------------------------------
        // Inject isFavorite + preferredEmployee into each cached item
        // (post-cache, never stored — never leak between users).
        // ------------------------------------------------------------------
        $items = array_map(function (array $row) use ($favoriteIds): object {
            $fav = $favoriteIds[$row['id']] ?? null;
            $row['is_favorite']             = $fav !== null;
            $row['preferred_employee_id']   = $fav['preferred_employee_id'] ?? null;
            $row['preferred_employee_name'] = $fav['preferred_employee_name'] ?? null;
            // Store the favorite's created_at for sorting below (null if not fav).
            $row['_fav_created_at'] = $fav['created_at'] ?? null;
            return (object) $row;
        }, $cached['items']);

        // ------------------------------------------------------------------
        // Favorites-first promotion (applied to every request, with or without
        // filters — see docblock for the chosen rule).
        //
        // Algorithm: stable partition into [favorites, rest], each sub-list
        // keeping its original order. Favorites are sorted by created_at ASC.
        // This is O(n) and allocation-efficient for typical page sizes (≤20).
        // ------------------------------------------------------------------
        if (! empty($favoriteIds)) {
            $favorites = [];
            $rest      = [];

            foreach ($items as $item) {
                if ($item->_fav_created_at !== null) {
                    $favorites[] = $item;
                } else {
                    $rest[] = $item;
                }
            }

            // Sort favorites oldest-added first (created_at ASC).
            usort($favorites, fn ($a, $b) => strcmp(
                (string) $a->_fav_created_at,
                (string) $b->_fav_created_at,
            ));

            $items = array_merge($favorites, $rest);
        }

        // Strip the internal sort key before handing to the Resource.
        foreach ($items as $item) {
            unset($item->_fav_created_at);
        }

        $paginator = new LengthAwarePaginator(
            $items,
            $cached['total'],
            $cached['per_page'],
            $cached['current_page'],
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return CompanyListResource::collection($paginator);
    }

    /**
     * GET /api/companies/{id}
     *
     * Returns full company details:
     *   - gallery photos
     *   - service categories with nested active services
     *   - active employees with user info and specialties
     *   - opening hours
     */
    /**
     * Columns returned for the detail view.
     * Explicitly excludes `location` (MySQL POINT binary blob) — it is never
     * needed by CompanyDetailResource and causes non-trivial serialisation
     * overhead even when not cast.
     */
    private const DETAIL_COLUMNS = [
        'id', 'name', 'description', 'phone', 'phone_secondary', 'email',
        'address', 'city', 'postal_code', 'country',
        'gender', 'booking_mode', 'rating', 'review_count', 'price_level',
        'profile_image_url', 'min_cancel_hours',
    ];

    public function show(int $id): JsonResponse
    {
        // Cache the fully-rendered JSON payload (plain PHP array) for 5 minutes.
        // We cache the *output* of CompanyDetailResource — not the Eloquent model —
        // so the cache driver never has to serialise Eloquent internals, enum
        // instances, or the binary POINT blob.
        $cached = Cache::remember("company:detail:{$id}", 300, function () use ($id): array|false {
            $company = Company::select(array_merge(self::DETAIL_COLUMNS, [
                DB::raw('ST_X(location) AS longitude'),
                DB::raw('ST_Y(location) AS latitude'),
            ]))->with([
                    'openingHours',
                    'galleryImages',
                    'serviceCategories.services',
                    'members' => fn ($q) => $q->where('is_active', true),
                    'members.user',
                    // Load which services each employee can perform — used by
                    // the share-with-preselected-employee flow to filter the
                    // visible service list on the recipient's salon page.
                    'members.services',
                ])
                ->find($id);

            if (! $company) {
                return false; // sentinel: company does not exist
            }

            // toArray() on the Resource gives us a plain PHP array with all
            // enum values already resolved — safe to serialise in any cache driver.
            return (new CompanyDetailResource($company))
                ->toArray(request());
        });

        if ($cached === false) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        // Inject isFavorite post-cache so the flag is always per-user fresh
        // and never persisted in (or leaked via) the shared company cache.
        // Public route → use sanctum guard explicitly to resolve bearer token.
        $userId = Auth::guard('sanctum')->id();
        $isFavorite = false;
        if ($userId) {
            $isFavorite = DB::table('company_favorites')
                ->where('user_id', $userId)
                ->where('company_id', $id)
                ->exists();
        }

        $cached['isFavorite'] = $isFavorite;

        return response()->json(['data' => $cached]);
    }

    /**
     * Resolves the cover photo URL for a listing card.
     *
     * Prefers the thumbnail of the first gallery image (sorted by sort_order ASC),
     * falls back to the company's profile_image_url when no gallery exists.
     */
    private function resolveListCoverPhoto(Company $company): ?string
    {
        if ($company->relationLoaded('galleryImages') && $company->galleryImages->isNotEmpty()) {
            $first = $company->galleryImages->first();

            if ($first) {
                $path = $first->thumbnail_path ?? $first->image_path;
                if ($path) {
                    return $this->normaliseStorageUrl($path);
                }
            }
        }

        return $company->profile_image_url;
    }

    /**
     * Returns a public URL for a storage path. If the path is already an
     * absolute URL (e.g. a seeded Unsplash link stored in image_path for
     * legacy fixtures), it is returned verbatim — no double-prefixing.
     */
    private function normaliseStorageUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return Storage::disk('public')->url($path);
    }

    /**
     * Compute a 4-day availability window for each company. Each entry is
     * a { date, available } pair — `available` is true if either the
     * morning or the afternoon has an open slot that day. Trimmed to 4
     * days because the home card only renders 4 chips.
     *
     * Without `$targetDate`, the window starts from the first date that has
     * at least one available slot (discovery mode — "next thing you can
     * book"). With `$targetDate` (shape `YYYY-MM-DD`), the window is
     * *centered around* the requested day: `[target-1, target, target+1,
     * target+2]`, clamped so we never surface a past day. This mirrors what
     * the user sees in the search header — if they ask for the 23rd, they
     * expect cards to showcase slots on and around the 23rd, not whatever
     * date falls next on the calendar.
     *
     * Days within the window that are closed or fully booked are emitted
     * with `available: false` so the Flutter card can render them greyed-out.
     *
     * @param  int[]  $companyIds
     * @return array<int, list<array{date: string, available: bool}>>
     */
    private function computeAvailability(array $companyIds, ?string $targetDate = null): array
    {
        if (empty($companyIds)) {
            return [];
        }

        $now   = Carbon::now();
        $today = Carbon::today();

        // Pre-compute the window start when a target is provided — the scan
        // must cover up to `target + 2` days, so bump the range when the user
        // is searching far in the future.
        $targetCarbon = null;
        if ($targetDate !== null && $targetDate !== '') {
            try {
                $targetCarbon = Carbon::createFromFormat('Y-m-d', $targetDate)->startOfDay();
            } catch (\Throwable $e) {
                // Silently ignore malformed inputs — request validation already
                // rejects bad shapes, this is belt-and-suspenders only.
                $targetCarbon = null;
            }
        }

        $scanDays = 37;
        if ($targetCarbon !== null) {
            $daysUntilTarget = $today->diffInDays($targetCarbon, false);
            // `+ 3` so `[target-1 .. target+2]` is always within the scanned
            // range, even when the user picks the very last selectable day.
            $scanDays = (int) max($scanDays, $daysUntilTarget + 3);
        }
        $scanEnd  = $today->copy()->addDays($scanDays);

        // --- Opening hours for every company in the batch ---
        $ohByCompany = [];
        $openingHours = CompanyOpeningHour::whereIn('company_id', $companyIds)->get();
        foreach ($openingHours as $oh) {
            $dow = $oh->day_of_week instanceof DayOfWeek
                ? $oh->day_of_week->value
                : (int) $oh->day_of_week;
            $ohByCompany[$oh->company_id][$dow] = $oh;
        }

        // --- Company days off (specific dates the salon is closed) ---
        // companyDaysOff[companyId][dateStr] = true
        $companyDaysOff = [];
        $daysOff = CompanyDayOff::whereIn('company_id', $companyIds)
            ->whereBetween('date', [$today->format('Y-m-d'), $scanEnd->format('Y-m-d')])
            ->get(['company_id', 'date']);

        foreach ($daysOff as $do) {
            $dateStr = $do->date instanceof Carbon
                ? $do->date->format('Y-m-d')
                : substr((string) $do->date, 0, 10);
            $companyDaysOff[$do->company_id][$dateStr] = true;
        }

        // --- Active employees per company ---
        $membersByCompany = [];
        $allMemberIds     = [];
        $members = CompanyUser::whereIn('company_id', $companyIds)
            ->where('is_active', true)
            ->get(['id', 'company_id']);

        foreach ($members as $m) {
            $membersByCompany[$m->company_id][] = $m->id;
            $allMemberIds[] = $m->id;
        }

        // --- Employee weekly schedules ---
        // empSchedule[empId][enumDow] = is_working (bool)
        $empSchedule = [];
        if (! empty($allMemberIds)) {
            $schedules = EmployeeSchedule::whereIn('company_user_id', $allMemberIds)->get();
            foreach ($schedules as $s) {
                $dow = $s->day_of_week instanceof DayOfWeek
                    ? $s->day_of_week->value
                    : (int) $s->day_of_week;
                $empSchedule[$s->company_user_id][$dow] = (bool) $s->is_working;
            }
        }

        // --- Employee days off (vacations, sick leave) ---
        // empDaysOff[empId][dateStr] = true
        $empDaysOff = [];
        if (! empty($allMemberIds)) {
            $edos = EmployeeDayOff::whereIn('company_user_id', $allMemberIds)
                ->whereBetween('date', [$today->format('Y-m-d'), $scanEnd->format('Y-m-d')])
                ->get(['company_user_id', 'date']);

            foreach ($edos as $edo) {
                $dateStr = $edo->date instanceof Carbon
                    ? $edo->date->format('Y-m-d')
                    : substr((string) $edo->date, 0, 10);
                $empDaysOff[$edo->company_user_id][$dateStr] = true;
            }
        }

        // --- Appointments for all relevant employees in the scan window ---
        $bookedByEmployee = [];
        if (! empty($allMemberIds)) {
            $rows = Appointment::whereIn('company_user_id', $allMemberIds)
                ->whereBetween('date', [$today->format('Y-m-d'), $scanEnd->format('Y-m-d')])
                ->whereIn('status', [
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::Pending->value,
                ])
                ->get(['company_user_id', 'date', 'start_time', 'end_time']);

            foreach ($rows as $row) {
                $dateStr = $row->date instanceof Carbon
                    ? $row->date->format('Y-m-d')
                    : substr((string) $row->date, 0, 10);
                $bookedByEmployee[$row->company_user_id][$dateStr][] = [
                    'start' => $row->start_time,
                    'end'   => $row->end_time,
                ];
            }
        }

        // --- Build availability per company ---
        $result = [];

        foreach ($companyIds as $companyId) {
            $companyOh      = $ohByCompany[$companyId] ?? [];
            $empIds         = $membersByCompany[$companyId] ?? [];

            $dailyFlags       = [];
            $firstAvailableDate = null;

            for ($offset = 0; $offset < $scanDays; $offset++) {
                $date      = $today->copy()->addDays($offset);
                $carbonDow = (int) $date->dayOfWeek;
                $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;
                $dateStr   = $date->format('Y-m-d');

                // Skip if company is closed this day of week
                $oh = $companyOh[$enumDow] ?? null;
                if (! $oh || $oh->is_closed || ! $oh->open_time || ! $oh->close_time) {
                    continue;
                }

                // Skip if company has a day off on this specific date
                if (isset($companyDaysOff[$companyId][$dateStr])) {
                    continue;
                }

                // Filter employees: only those working this day and not on day off
                $availableEmpIds = [];
                foreach ($empIds as $empId) {
                    // Employee day off on this specific date
                    if (isset($empDaysOff[$empId][$dateStr])) {
                        continue;
                    }
                    // Employee weekly schedule: not working this day of week
                    // (if no schedule is defined, assume the employee works every day)
                    if (isset($empSchedule[$empId][$enumDow]) && ! $empSchedule[$empId][$enumDow]) {
                        continue;
                    }
                    $availableEmpIds[] = $empId;
                }

                $totalAvailable = count($availableEmpIds);

                // No employees available this day → skip entirely
                if ($totalAvailable === 0 && count($empIds) > 0) {
                    continue;
                }

                $openTime  = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $oh->open_time);
                $closeTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $oh->close_time);

                $hasMorning   = false;
                $hasAfternoon = false;
                $cursor       = $openTime->copy();

                while ($cursor->lt($closeTime)) {
                    if ($cursor->lte($now)) {
                        $cursor->addMinutes(30);
                        continue;
                    }

                    $slotTime     = $cursor->format('H:i:s');
                    $blockedCount = 0;

                    foreach ($availableEmpIds as $empId) {
                        $windows = $bookedByEmployee[$empId][$dateStr] ?? [];
                        foreach ($windows as $booked) {
                            if ($slotTime >= $booked['start'] && $slotTime < $booked['end']) {
                                $blockedCount++;
                                break;
                            }
                        }
                    }

                    $isBlocked = $totalAvailable > 0 && $blockedCount >= $totalAvailable;

                    if (! $isBlocked) {
                        if ($slotTime < '12:00:00') {
                            $hasMorning = true;
                        } else {
                            $hasAfternoon = true;
                        }
                    }

                    $cursor->addMinutes(30);
                }

                $dailyFlags[$dateStr] = [
                    'morning'   => $hasMorning,
                    'afternoon' => $hasAfternoon,
                ];

                if ($firstAvailableDate === null && ($hasMorning || $hasAfternoon)) {
                    $firstAvailableDate = $date->copy();
                }
            }

            // Build a short 4-day window. The home card only renders 4
            // chips, so we trim the payload and collapse morning/afternoon
            // into a single `available` flag (true if either half of the
            // day has a free slot).
            //
            // Two modes:
            //   - target given  → centre on target-1: `[target-1 .. target+2]`,
            //                     clamped to today so we never show the past.
            //   - no target     → start from the first day with any free slot
            //                     (discovery / "next available").
            $availability = [];
            $windowStart  = null;

            if ($targetCarbon !== null) {
                $candidate   = $targetCarbon->copy()->subDay();
                $windowStart = $candidate->lt($today) ? $today->copy() : $candidate;
            } elseif ($firstAvailableDate !== null) {
                $windowStart = $firstAvailableDate;
            }

            if ($windowStart !== null) {
                for ($i = 0; $i < 4; $i++) {
                    $date    = $windowStart->copy()->addDays($i);
                    $dateStr = $date->format('Y-m-d');
                    $flags   = $dailyFlags[$dateStr] ?? ['morning' => false, 'afternoon' => false];

                    $availability[] = [
                        'date'      => $dateStr,
                        'available' => ($flags['morning'] ?? false) || ($flags['afternoon'] ?? false),
                    ];
                }
            }

            $result[$companyId] = $availability;
        }

        return $result;
    }

    /**
     * GET /api/companies/{id}/availability
     *
     * Returns a day-by-day availability status for the next 14 days starting
     * from today. Each entry carries a human-readable day name (in French) and
     * one of the following statuses:
     *
     *   available    — day has at least one free slot
     *   closed       — company is closed this day (opening-hour record or company day-off)
     *   day_off      — the requested employee has a day-off on this date
     *   not_working  — the requested employee's schedule marks this day as not worked
     *   full         — all slots for the day are booked
     *
     * When no employee_id is provided "day_off" and "not_working" are never
     * emitted. A day is "full" only when every active employee is booked for
     * every 30-minute slot.
     *
     * Query params:
     *   - employee_id — optional, company_user.id
     *   - service_id  — optional, used to determine slot duration and filter
     *                   employees linked to that service
     */
    public function availability(GetAvailabilityRequest $request, int $id): JsonResponse
    {
        $company = Company::select('id')
            ->with([
                'openingHours:id,company_id,day_of_week,open_time,close_time,is_closed',
                'members:id,company_id,is_active',
            ])
            ->find($id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $today      = Carbon::today();
        $now        = Carbon::now();
        $employeeId = $request->validated('employee_id');
        $serviceId  = $request->validated('service_id');

        // Resolve service duration (default 30 min when no service provided)
        $serviceDuration = 30;
        if ($serviceId) {
            $service = Service::find((int) $serviceId);
            if ($service) {
                $serviceDuration = (int) $service->duration;
            }
        }

        // --- Eligible employees ---
        $allActiveIds = $company->members
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $activeEmployeeIds = $allActiveIds;

        if ($serviceId) {
            // JOIN is faster than a correlated whereHas subquery — single scan
            // against the employee_service index instead of N dependent queries.
            $linkedIds = CompanyUser::whereIn('company_user.id', $allActiveIds)
                ->join('employee_service', 'employee_service.company_user_id', '=', 'company_user.id')
                ->where('employee_service.service_id', (int) $serviceId)
                ->pluck('company_user.id')
                ->all();

            if (! empty($linkedIds)) {
                $activeEmployeeIds = $linkedIds;
            }
        }

        $totalEmployees = count($activeEmployeeIds);
        $scanEnd        = $today->copy()->addDays(13);

        // The set of employees whose data we need to load
        $employeeScope = $employeeId ? [(int) $employeeId] : $activeEmployeeIds;

        // --- Company days off ---
        $companyDaysOff = [];
        $companyDayOffRows = \App\Models\CompanyDayOff::where('company_id', $id)
            ->whereBetween('date', [$today->format('Y-m-d'), $scanEnd->format('Y-m-d')])
            ->get(['date']);

        foreach ($companyDayOffRows as $cdo) {
            $dateStr = $cdo->date instanceof Carbon
                ? $cdo->date->format('Y-m-d')
                : substr((string) $cdo->date, 0, 10);
            $companyDaysOff[$dateStr] = true;
        }

        // --- Employee days off ---
        $empDaysOff = [];
        if (! empty($employeeScope)) {
            $edos = EmployeeDayOff::whereIn('company_user_id', $employeeScope)
                ->whereBetween('date', [$today->format('Y-m-d'), $scanEnd->format('Y-m-d')])
                ->get(['company_user_id', 'date']);

            foreach ($edos as $edo) {
                $dateStr = $edo->date instanceof Carbon
                    ? $edo->date->format('Y-m-d')
                    : substr((string) $edo->date, 0, 10);
                $empDaysOff[$edo->company_user_id][$dateStr] = true;
            }
        }

        // --- Employee schedules ---
        $scheduleByEmployee = [];
        if (! empty($employeeScope)) {
            $scheduleRows = EmployeeSchedule::whereIn('company_user_id', $employeeScope)
                ->get(['company_user_id', 'day_of_week', 'start_time', 'end_time', 'is_working']);

            foreach ($scheduleRows as $sr) {
                $rawDow = $sr->getRawOriginal('day_of_week');
                $scheduleByEmployee[$sr->company_user_id][(int) $rawDow] = [
                    'is_working' => (bool) $sr->is_working,
                    'start'      => $sr->start_time,
                    'end'        => $sr->end_time,
                ];
            }
        }

        // --- Bookings ---
        $bookedByEmployee = [];
        if (! empty($employeeScope)) {
            $rows = Appointment::query()
                ->whereIn('company_user_id', $employeeScope)
                ->whereBetween('date', [$today->format('Y-m-d'), $scanEnd->format('Y-m-d')])
                ->whereIn('status', [
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::Pending->value,
                ])
                ->get(['company_user_id', 'date', 'start_time', 'end_time']);

            foreach ($rows as $row) {
                $dateStr = $row->date instanceof Carbon
                    ? $row->date->format('Y-m-d')
                    : substr((string) $row->date, 0, 10);
                $bookedByEmployee[$row->company_user_id][$dateStr][] = [
                    'start' => $row->start_time,
                    'end'   => $row->end_time,
                ];
            }
        }

        // --- Breaks ---
        $breaksByEmployee = [];
        if (! empty($employeeScope)) {
            $breakRows = EmployeeBreak::whereIn('company_user_id', $employeeScope)
                ->get(['company_user_id', 'day_of_week', 'start_time', 'end_time']);

            foreach ($breakRows as $br) {
                $rawDow = $br->getRawOriginal('day_of_week');
                $breaksByEmployee[$br->company_user_id][] = [
                    'day_of_week' => $rawDow !== null ? (int) $rawDow : null,
                    'start'       => $br->start_time,
                    'end'         => $br->end_time,
                ];
            }
        }

        // --- Opening hours lookup ---
        $openingByDow = [];
        foreach ($company->openingHours as $oh) {
            $dow = $oh->day_of_week instanceof DayOfWeek
                ? $oh->day_of_week->value
                : (int) $oh->day_of_week;
            $openingByDow[$dow] = $oh;
        }

        // French day names indexed by Carbon's dayOfWeek (0 = Sunday … 6 = Saturday)
        $frenchDayNames = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
        ];

        // Convert an "H:i:s" string to integer minutes-since-midnight.
        // Used throughout the slot loop so we never construct Carbon objects
        // inside the hot path — integer arithmetic is ~100x faster.
        $toMinutes = static function (string $time): int {
            [$h, $m] = explode(':', $time);
            return (int) $h * 60 + (int) $m;
        };

        // Current time as integer minutes (for "skip past slots" check).
        $nowMinutes = $now->hour * 60 + $now->minute;
        // Today's date string for the past-slot comparison.
        $todayStr = $today->format('Y-m-d');

        // --- Helper: is a single employee blocked for a given slot? ---
        // Works entirely with integer minutes — no Carbon allocation.
        $isEmployeeUnavailable = static function (
            int    $empId,
            string $dateStr,
            int    $enumDow,
            int    $slotStartMin,
            int    $slotEndMin,
            array  $bookedByEmployee,
            array  $breaksByDowEmployee, // pre-keyed by [empId][enumDow]
        ): bool {
            $windows = $bookedByEmployee[$empId][$dateStr] ?? [];
            foreach ($windows as $booked) {
                if ($booked['start'] < $slotEndMin && $booked['end'] > $slotStartMin) {
                    return true;
                }
            }

            // Breaks are already filtered to this day-of-week before the loop.
            // Overlap check: [slotStart, slotEnd) vs [brkStart, brkEnd). The
            // former "slot-start-inside-break" check would let a 30-min
            // service at 11:45 slip past a 12:00-13:00 break.
            $breaks = $breaksByDowEmployee[$empId][$enumDow] ?? [];
            foreach ($breaks as $brk) {
                if ($brk['start'] < $slotEndMin && $brk['end'] > $slotStartMin) {
                    return true;
                }
            }

            return false;
        };

        // Pre-convert all booked windows to integer minutes so comparisons
        // inside the slot loop never touch string parsing again.
        $bookedByEmployeeInt = [];
        foreach ($bookedByEmployee as $empId => $dateMap) {
            foreach ($dateMap as $dateStr => $windows) {
                foreach ($windows as $w) {
                    $bookedByEmployeeInt[$empId][$dateStr][] = [
                        'start' => $toMinutes($w['start']),
                        'end'   => $toMinutes($w['end']),
                    ];
                }
            }
        }

        // Pre-convert and index breaks by [empId][enumDow].
        // A null day_of_week means the break applies every day — expand it to
        // all 7 day slots so the inner loop never needs the null check.
        $breaksByDowEmployee = [];
        foreach ($breaksByEmployee as $empId => $breaks) {
            foreach ($breaks as $brk) {
                $startMin = $toMinutes($brk['start']);
                $endMin   = $toMinutes($brk['end']);
                if ($brk['day_of_week'] === null) {
                    for ($d = 0; $d <= 6; $d++) {
                        $breaksByDowEmployee[$empId][$d][] = ['start' => $startMin, 'end' => $endMin];
                    }
                } else {
                    $breaksByDowEmployee[$empId][$brk['day_of_week']][] = ['start' => $startMin, 'end' => $endMin];
                }
            }
        }

        // --- Build 14-day response ---
        $days = [];

        for ($offset = 0; $offset <= 13; $offset++) {
            $date      = $today->copy()->addDays($offset);
            $dateStr   = $date->format('Y-m-d');
            $carbonDow = (int) $date->dayOfWeek;
            $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;
            $dayName   = $frenchDayNames[$carbonDow];

            // 1. Company closed this day of week
            $oh = $openingByDow[$enumDow] ?? null;
            if (! $oh || $oh->is_closed || ! $oh->open_time || ! $oh->close_time) {
                $days[] = ['date' => $dateStr, 'dayName' => $dayName, 'status' => 'closed'];
                continue;
            }

            // 2. Company day-off on this specific date
            if (isset($companyDaysOff[$dateStr])) {
                $days[] = ['date' => $dateStr, 'dayName' => $dayName, 'status' => 'closed'];
                continue;
            }

            // 3. Employee-specific checks (only when employee_id is given)
            if ($employeeId) {
                // Employee has a day-off on this date
                if (isset($empDaysOff[(int) $employeeId][$dateStr])) {
                    $days[] = ['date' => $dateStr, 'dayName' => $dayName, 'status' => 'day_off'];
                    continue;
                }

                // Employee's weekly schedule: not working this day
                $empSched = $scheduleByEmployee[(int) $employeeId][$enumDow] ?? null;
                if ($empSched !== null && ! $empSched['is_working']) {
                    $days[] = ['date' => $dateStr, 'dayName' => $dayName, 'status' => 'not_working'];
                    continue;
                }

                // Resolve employee's effective hours for this day
                $dayOpenTime  = ($empSched !== null && $empSched['start'])
                    ? $empSched['start']
                    : $oh->open_time;
                $dayCloseTime = ($empSched !== null && $empSched['end'])
                    ? $empSched['end']
                    : $oh->close_time;
            } else {
                $dayOpenTime  = $oh->open_time;
                $dayCloseTime = $oh->close_time;
            }

            // 4. Count free slots for this day using integer-minute arithmetic.
            // No Carbon objects are allocated inside this loop.
            $openMin  = $toMinutes($dayOpenTime);
            $closeMin = $toMinutes($dayCloseTime);
            $freeCount = 0;

            for ($slotMin = $openMin; $slotMin + $serviceDuration <= $closeMin; $slotMin += 30) {
                // Skip slots that are already in the past (today only)
                if ($dateStr === $todayStr && $slotMin <= $nowMinutes) {
                    continue;
                }

                $slotEndMin = $slotMin + $serviceDuration;
                $isBlocked  = false;

                if ($employeeId) {
                    $isBlocked = $isEmployeeUnavailable(
                        (int) $employeeId,
                        $dateStr,
                        $enumDow,
                        $slotMin,
                        $slotEndMin,
                        $bookedByEmployeeInt,
                        $breaksByDowEmployee,
                    );
                } else {
                    $unavailableCount = 0;
                    foreach ($activeEmployeeIds as $empId) {
                        $empSched = $scheduleByEmployee[$empId][$enumDow] ?? null;
                        if ($empSched !== null && ! $empSched['is_working']) {
                            $unavailableCount++;
                            continue;
                        }
                        if (isset($empDaysOff[$empId][$dateStr])) {
                            $unavailableCount++;
                            continue;
                        }
                        if ($isEmployeeUnavailable($empId, $dateStr, $enumDow, $slotMin, $slotEndMin, $bookedByEmployeeInt, $breaksByDowEmployee)) {
                            $unavailableCount++;
                        }
                    }
                    $isBlocked = $totalEmployees > 0 && $unavailableCount >= $totalEmployees;
                }

                if (! $isBlocked) {
                    $freeCount++;
                }
            }

            if ($freeCount > 0) {
                $days[] = [
                    'date'       => $dateStr,
                    'dayName'    => $dayName,
                    'status'     => 'available',
                    'slotsCount' => $freeCount,
                ];
            } else {
                $days[] = [
                    'date'       => $dateStr,
                    'dayName'    => $dayName,
                    'status'     => 'full',
                    'slotsCount' => 0,
                ];
            }
        }

        return response()->json(['data' => $days]);
    }

    /**
     * GET /api/companies/{id}/slots
     *
     * Returns available time slots for ONE specific day. Slots are spaced
     * every 30 minutes but each slot window is `service.duration` minutes
     * wide. A slot is only returned when the full service duration fits
     * before closing time AND no existing booking overlaps any part of that
     * window.
     *
     * Rules:
     *   - Slots in the past are skipped
     *   - A slot must fit entirely before closing time
     *   - With employee_id:
     *       - Uses the employee's custom schedule times when set
     *       - A slot is blocked when the employee has an overlapping booking
     *       - A slot is blocked when the employee has a break covering that time
     *   - Without employee_id ("sans préférence"):
     *       - A slot is blocked only when ALL active employees are unavailable
     *         (booked OR on break) at that time
     *
     * Query params:
     *   - date        — required, Y-m-d
     *   - employee_id — optional, company_user.id
     *   - service_id  — optional, used to determine slot duration and filter
     *                   employees linked to that service
     */
    public function slots(GetSlotsRequest $request, int $id): JsonResponse
    {
        $company = Company::with(['openingHours', 'members'])->find($id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $mode = $company->booking_mode instanceof BookingMode
            ? $company->booking_mode
            : BookingMode::from((string) ($company->booking_mode ?? 'employee_based'));

        if ($mode === BookingMode::CapacityBased) {
            return $this->capacityBasedSlots($request, $company);
        }

        $serviceId  = $request->validated('service_id');
        $employeeId = $request->validated('employee_id');

        // Resolve service duration (default 30 min when no service provided)
        $serviceDuration = 30;
        if ($serviceId) {
            $service = Service::find((int) $serviceId);
            if ($service) {
                $serviceDuration = (int) $service->duration;
            }
        }

        $rawDate = $request->validated('date');
        $date    = $rawDate
            ? Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay()
            : Carbon::today();
        $dateStr = $date->format('Y-m-d');
        $now     = Carbon::now();

        // Convert Carbon dayOfWeek (0=Sun) to enumDow (0=Mon … 6=Sun)
        $carbonDow = (int) $date->dayOfWeek;
        $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;

        // --- Eligible employees ---
        $allActiveIds = $company->members
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $activeEmployeeIds = $allActiveIds;

        if ($serviceId) {
            // JOIN is faster than a correlated whereHas subquery — single scan
            // against the employee_service index instead of N dependent queries.
            $linkedIds = CompanyUser::whereIn('company_user.id', $allActiveIds)
                ->join('employee_service', 'employee_service.company_user_id', '=', 'company_user.id')
                ->where('employee_service.service_id', (int) $serviceId)
                ->pluck('company_user.id')
                ->all();

            if (! empty($linkedIds)) {
                $activeEmployeeIds = $linkedIds;
            }
        }

        $totalEmployees = count($activeEmployeeIds);
        $employeeScope  = $employeeId ? [(int) $employeeId] : $activeEmployeeIds;

        // --- Opening hours for this day ---
        $openingByDow = [];
        foreach ($company->openingHours as $oh) {
            $dow = $oh->day_of_week instanceof DayOfWeek
                ? $oh->day_of_week->value
                : (int) $oh->day_of_week;
            $openingByDow[$dow] = $oh;
        }

        $openingHour = $openingByDow[$enumDow] ?? null;

        if (! $openingHour || $openingHour->is_closed || ! $openingHour->open_time || ! $openingHour->close_time) {
            return response()->json(['data' => []]);
        }

        // --- Company day-off check ---
        $isCompanyDayOff = \App\Models\CompanyDayOff::where('company_id', $id)
            ->where('date', $dateStr)
            ->exists();

        if ($isCompanyDayOff) {
            return response()->json(['data' => []]);
        }

        // --- Employee-specific early exits ---
        if ($employeeId) {
            // Employee day-off
            $isEmpDayOff = EmployeeDayOff::where('company_user_id', (int) $employeeId)
                ->where('date', $dateStr)
                ->exists();

            if ($isEmpDayOff) {
                return response()->json(['data' => []]);
            }
        }

        // --- Load bookings ---
        $bookedByEmployee = [];
        if (! empty($employeeScope)) {
            $rows = Appointment::query()
                ->whereIn('company_user_id', $employeeScope)
                ->where('date', $dateStr)
                ->whereIn('status', [
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::Pending->value,
                ])
                ->get(['company_user_id', 'start_time', 'end_time']);

            foreach ($rows as $row) {
                $bookedByEmployee[$row->company_user_id][] = [
                    'start' => $row->start_time,
                    'end'   => $row->end_time,
                ];
            }
        }

        // --- Load breaks ---
        $breaksByEmployee = [];
        if (! empty($employeeScope)) {
            $breakRows = EmployeeBreak::whereIn('company_user_id', $employeeScope)
                ->get(['company_user_id', 'day_of_week', 'start_time', 'end_time']);

            foreach ($breakRows as $br) {
                $rawDow = $br->getRawOriginal('day_of_week');
                $breaksByEmployee[$br->company_user_id][] = [
                    'day_of_week' => $rawDow !== null ? (int) $rawDow : null,
                    'start'       => $br->start_time,
                    'end'         => $br->end_time,
                ];
            }
        }

        // --- Load custom employee schedules ---
        $scheduleByEmployee = [];
        if (! empty($employeeScope)) {
            $scheduleRows = EmployeeSchedule::whereIn('company_user_id', $employeeScope)
                ->get(['company_user_id', 'day_of_week', 'start_time', 'end_time', 'is_working']);

            foreach ($scheduleRows as $sr) {
                $rawDow = $sr->getRawOriginal('day_of_week');
                $scheduleByEmployee[$sr->company_user_id][(int) $rawDow] = [
                    'is_working' => (bool) $sr->is_working,
                    'start'      => $sr->start_time,
                    'end'        => $sr->end_time,
                ];
            }
        }

        // --- Helper: is a single employee unavailable for a given slot? ---
        $isEmployeeUnavailable = function (
            int    $empId,
            string $slotStartTime,
            string $slotEndTime,
        ) use ($bookedByEmployee, $breaksByEmployee, $enumDow): bool {
            // Booking overlap
            $windows = $bookedByEmployee[$empId] ?? [];
            foreach ($windows as $booked) {
                if ($booked['start'] < $slotEndTime && $booked['end'] > $slotStartTime) {
                    return true;
                }
            }

            // Break overlap — use full [slotStart, slotEnd) vs [brkStart, brkEnd).
            // Start-inside-break was buggy: a 30-min service at 11:45 would
            // pass a 12:00-13:00 break (slot runs 11:45-12:15, entering break).
            $breaks = $breaksByEmployee[$empId] ?? [];
            foreach ($breaks as $brk) {
                if ($brk['day_of_week'] !== null && $brk['day_of_week'] !== $enumDow) {
                    continue;
                }
                if ($brk['start'] < $slotEndTime && $brk['end'] > $slotStartTime) {
                    return true;
                }
            }

            return false;
        };

        // --- Resolve effective open/close times ---
        if ($employeeId) {
            $empSched = $scheduleByEmployee[(int) $employeeId][$enumDow] ?? null;

            if ($empSched !== null) {
                if (! $empSched['is_working']) {
                    return response()->json(['data' => []]);
                }
                $dayOpenTime  = $empSched['start'];
                $dayCloseTime = $empSched['end'];
            } else {
                $dayOpenTime  = $openingHour->open_time;
                $dayCloseTime = $openingHour->close_time;
            }
        } else {
            $dayOpenTime  = $openingHour->open_time;
            $dayCloseTime = $openingHour->close_time;
        }

        $openTime  = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $dayOpenTime);
        $closeTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $dayCloseTime);
        $cursor    = $openTime->copy();
        $slots     = [];

        // --- Load employee day-offs for "sans préférence" mode ---
        $empDaysOffForDate = [];
        if (! $employeeId && ! empty($activeEmployeeIds)) {
            $edos = EmployeeDayOff::whereIn('company_user_id', $activeEmployeeIds)
                ->where('date', $dateStr)
                ->pluck('company_user_id')
                ->all();

            foreach ($edos as $empId) {
                $empDaysOffForDate[$empId] = true;
            }
        }

        while ($cursor->lt($closeTime)) {
            if ($cursor->lte($now)) {
                $cursor->addMinutes(30);
                continue;
            }

            $slotEnd = $cursor->copy()->addMinutes($serviceDuration);
            if ($slotEnd->gt($closeTime)) {
                break;
            }

            $slotStartTime = $cursor->format('H:i:s');
            $slotEndTime   = $slotEnd->format('H:i:s');
            $isBlocked     = false;

            if ($employeeId) {
                $isBlocked = $isEmployeeUnavailable((int) $employeeId, $slotStartTime, $slotEndTime);
            } else {
                $unavailableCount = 0;
                foreach ($activeEmployeeIds as $empId) {
                    $empSched = $scheduleByEmployee[$empId][$enumDow] ?? null;
                    if ($empSched !== null && ! $empSched['is_working']) {
                        $unavailableCount++;
                        continue;
                    }
                    if (isset($empDaysOffForDate[$empId])) {
                        $unavailableCount++;
                        continue;
                    }
                    if ($isEmployeeUnavailable($empId, $slotStartTime, $slotEndTime)) {
                        $unavailableCount++;
                    }
                }
                $isBlocked = $totalEmployees > 0 && $unavailableCount >= $totalEmployees;
            }

            if (! $isBlocked) {
                $slots[] = [
                    'dateTime'   => $cursor->format('Y-m-d\TH:i:s'),
                    'employeeId' => $employeeId ? (string) $employeeId : null,
                ];
            }

            $cursor->addMinutes(30);
        }

        return response()->json(['data' => $slots]);
    }

    /**
     * Slot generation for capacity-based (Type 2) companies.
     *
     * Contract: { dateTime, serviceId, available, remaining, max }
     *   - remaining = max_concurrent − count(bookings pending/confirmed/rejected for service+slot)
     *     (cancelled / no_show release capacity — the visit never happened)
     *   - capacity override for date → max = min(service.max_concurrent, override.capacity)
     *   - break window or day_off → available:false, remaining:0
     *
     * Query param `service_id` is required for capacity-based mode; without it
     * we cannot compute remaining capacity per service.
     */
    private function capacityBasedSlots(GetSlotsRequest $request, Company $company): JsonResponse
    {
        $serviceId = $request->validated('service_id');

        if (! $serviceId) {
            return response()->json([
                'success' => false,
                'message' => 'service_id is required for capacity-based companies.',
            ], 422);
        }

        $service = Service::where('id', (int) $serviceId)
            ->where('company_id', $company->id)
            ->first();

        if (! $service) {
            return response()->json(['data' => []]);
        }

        $rawDate = $request->validated('date');
        $date    = $rawDate
            ? Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay()
            : Carbon::today();
        $dateStr = $date->format('Y-m-d');
        $now     = Carbon::now();

        $carbonDow = (int) $date->dayOfWeek;
        $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;

        // --- Opening hours ---
        $openingByDow = [];
        foreach ($company->openingHours as $oh) {
            $dow = $oh->day_of_week instanceof DayOfWeek
                ? $oh->day_of_week->value
                : (int) $oh->day_of_week;
            $openingByDow[$dow] = $oh;
        }

        $openingHour = $openingByDow[$enumDow] ?? null;

        if (! $openingHour || $openingHour->is_closed || ! $openingHour->open_time || ! $openingHour->close_time) {
            return response()->json(['data' => []]);
        }

        // --- Company day-off ---
        $isDayOff = CompanyDayOff::where('company_id', $company->id)
            ->where('date', $dateStr)
            ->exists();

        if ($isDayOff) {
            return response()->json(['data' => []]);
        }

        // --- Service max_concurrent + capacity override ---
        $baseCap = $service->max_concurrent; // null = unlimited

        $override = CompanyCapacityOverride::where('company_id', $company->id)
            ->where('date', $dateStr)
            ->first();

        $effectiveMax = null;
        if ($baseCap !== null) {
            $effectiveMax = $override ? min($baseCap, $override->capacity) : $baseCap;
        }

        // --- Company breaks for this day ---
        $breaks = CompanyBreak::where('company_id', $company->id)
            ->where(function ($q) use ($enumDow) {
                $q->whereNull('day_of_week')
                  ->orWhere('day_of_week', $enumDow);
            })
            ->get(['start_time', 'end_time']);

        // --- Booked slots for this service + date ---
        // Pending / Confirmed / Rejected all hold capacity:
        //   • pending & confirmed = the visit is scheduled
        //   • rejected = owner refused because they're at capacity; the slot
        //     stays blocked so no one else can book at the same time.
        // Cancelled + no_show release capacity (the visit never took place,
        // or was dropped by the client — slot reopens).
        $bookedRows = Appointment::where('company_id', $company->id)
            ->where('service_id', $service->id)
            ->where('date', $dateStr)
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Rejected->value,
            ])
            ->get(['start_time', 'end_time']);

        $serviceDuration = (int) $service->duration;

        $openTime  = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $openingHour->open_time);
        $closeTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $openingHour->close_time);
        $cursor    = $openTime->copy();
        $slots     = [];

        while ($cursor->lt($closeTime)) {
            if ($cursor->lte($now)) {
                $cursor->addMinutes(30);
                continue;
            }

            $slotEnd = $cursor->copy()->addMinutes($serviceDuration);
            if ($slotEnd->gt($closeTime)) {
                break;
            }

            $slotStartStr = $cursor->format('H:i:s');
            $slotEndStr   = $slotEnd->format('H:i:s');

            // Check company breaks — overlap of [slotStart, slotEnd) with break.
            $onBreak = false;
            foreach ($breaks as $brk) {
                if ($brk->start_time < $slotEndStr && $brk->end_time > $slotStartStr) {
                    $onBreak = true;
                    break;
                }
            }

            if ($onBreak) {
                $slots[] = [
                    'dateTime'  => $cursor->format('Y-m-d\TH:i:s'),
                    'serviceId' => (string) $service->id,
                    'available' => false,
                    'remaining' => 0,
                    'max'       => $effectiveMax,
                ];
                $cursor->addMinutes(30);
                continue;
            }

            // Count booked slots overlapping this window
            $bookedCount = 0;
            foreach ($bookedRows as $booked) {
                if ($booked->start_time < $slotEndStr && $booked->end_time > $slotStartStr) {
                    $bookedCount++;
                }
            }

            $remaining = $effectiveMax !== null ? max(0, $effectiveMax - $bookedCount) : null;
            $available = $effectiveMax === null ? true : $remaining > 0;

            $slots[] = [
                'dateTime'  => $cursor->format('Y-m-d\TH:i:s'),
                'serviceId' => (string) $service->id,
                'available' => $available,
                'remaining' => $remaining,
                'max'       => $effectiveMax,
            ];

            $cursor->addMinutes(30);
        }

        return response()->json(['data' => $slots]);
    }

    /**
     * GET /api/companies/{id}/employees
     *
     * Returns the list of active employees for a company.
     * Used by the booking flow to let the client pick a staff member.
     */
    public function employees(int $id): JsonResponse
    {
        $company = Company::with(['members.user', 'members.services'])->find($id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $employees = $company->members
            ->where('is_active', true)
            ->map(fn ($member) => [
                'id'          => (string) $member->id,
                // userId — what the mobile app uses to match the logged-in
                // user to an employee (share link `?employee=<userId>`).
                'userId'      => $member->user ? (string) $member->user->id : null,
                'name'        => $member->user
                    ? trim($member->user->first_name . ' ' . $member->user->last_name)
                    : null,
                'photoUrl'    => $member->user?->profile_image_url ?? $member->profile_photo,
                'specialties' => $member->specialties ?? [],
                'serviceIds'  => $member->services->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'role'        => $member->role instanceof \BackedEnum
                    ? $member->role->value
                    : $member->role,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $employees,
        ]);
    }
}
