<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Http\Requests\Company\GetAvailabilityRequest;
use App\Http\Requests\Company\GetSlotsRequest;
use App\Http\Requests\Company\ListCompaniesRequest;
use App\Http\Resources\CompanyDetailResource;
use App\Http\Resources\CompanyListResource;
use App\Http\Resources\CompanyResource;
use App\Models\Appointment;
use App\Models\Company;
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
use Illuminate\Support\Facades\Cache;

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
        'gender',
    ];

    /**
     * GET /api/companies
     *
     * Returns a paginated list of companies (lightweight card view).
     * Results are cached per unique filter combination for 5 minutes to
     * eliminate repeated DB hits on identical queries.
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

        // Build a deterministic cache key from the full filter state so that
        // different filter combinations never share the same cached result.
        $cacheKey = 'companies:list:' . md5(serialize([
            $validated['search'] ?? null,
            $validated['city']   ?? null,
            $validated['gender'] ?? null,
            $validated['date']   ?? null,
            $validated['page']   ?? 1,
        ]));

        // Cache a plain-PHP array (not Eloquent objects) so the file driver can
        // serialize and deserialize safely. Eloquent paginators cannot be stored
        // in the file cache because PHP cannot reconstruct model instances from
        // serialized closures and lazy collections.
        $cached = Cache::remember($cacheKey, 300, function () use ($validated): array {
            $query = Company::select(self::LIST_COLUMNS);

            // --- Search: LIKE on name or address (FULLTEXT index also exists) ---
            if (! empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('address', 'LIKE', '%' . $search . '%');
                });
            }

            // --- City filter (case-insensitive exact match via index) ---
            if (! empty($validated['city'])) {
                $query->whereRaw('LOWER(city) = ?', [mb_strtolower($validated['city'])]);
            }

            // --- Gender filter ---
            // A salon with gender "both" is always included regardless of the filter.
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

            $items = $paginator->items() ?: [];
            $companyIds = array_map(fn ($c) => $c->id, $items);

            // Compute 7-day availability starting from each company's first available date
            $availability = $this->computeAvailability($companyIds);

            $itemArrays = [];
            foreach ($items as $c) {
                $arr = $c->toArray();
                $arr['availability'] = $availability[$c->id] ?? [];
                $itemArrays[] = $arr;
            }

            return [
                'items'        => $itemArrays,
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ];
        });

        // The cached items are plain PHP arrays (serialized via toArray()).
        // CompanyListResource uses $this->id / $this->name etc. via JsonResource's
        // DelegatesToResource magic, which requires an object — not an array.
        // Cast each item to stdClass so property access works correctly without
        // having to re-hydrate full Eloquent model instances from the cache.
        $items = array_map(fn (array $row) => (object) $row, $cached['items']);

        // Rebuild a LengthAwarePaginator from the cached data so that
        // CompanyListResource::collection() emits correct pagination meta.
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
        'id', 'name', 'description', 'phone', 'email',
        'address', 'city', 'postal_code', 'country',
        'gender', 'rating', 'review_count', 'price_level',
        'profile_image_url',
    ];

    public function show(int $id): JsonResponse
    {
        // Cache the fully-rendered JSON payload (plain PHP array) for 5 minutes.
        // We cache the *output* of CompanyDetailResource — not the Eloquent model —
        // so the cache driver never has to serialise Eloquent internals, enum
        // instances, or the binary POINT blob.
        $cached = Cache::remember("company:detail:{$id}", 300, function () use ($id): array|false {
            $company = Company::select(self::DETAIL_COLUMNS)
                ->with([
                    'openingHours',
                    'galleryImages',
                    'serviceCategories.services',
                    'members' => fn ($q) => $q->where('is_active', true),
                    'members.user',
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

        return response()->json(['data' => $cached]);
    }

    /**
     * Compute a 7-day morning/afternoon availability window for each company.
     *
     * The window starts from the first date that has at least one available slot.
     * Days within the window that are closed or fully booked are marked as
     * disabled (morning: false, afternoon: false) so the Flutter card can
     * render them greyed-out.
     *
     * @param  int[]  $companyIds
     * @return array<int, list<array{date: string, morning: bool, afternoon: bool}>>
     */
    private function computeAvailability(array $companyIds): array
    {
        if (empty($companyIds)) {
            return [];
        }

        $now   = Carbon::now();
        $today = Carbon::today();
        $scanDays = 37;
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

            // Build the 7-day window from the first available date
            $availability = [];
            if ($firstAvailableDate !== null) {
                for ($i = 0; $i < 7; $i++) {
                    $date    = $firstAvailableDate->copy()->addDays($i);
                    $dateStr = $date->format('Y-m-d');
                    $flags   = $dailyFlags[$dateStr] ?? ['morning' => false, 'afternoon' => false];

                    $availability[] = [
                        'date'      => $dateStr,
                        'morning'   => $flags['morning'],
                        'afternoon' => $flags['afternoon'],
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
            $breaks = $breaksByDowEmployee[$empId][$enumDow] ?? [];
            foreach ($breaks as $brk) {
                if ($brk['start'] <= $slotStartMin && $slotStartMin < $brk['end']) {
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

            // Break overlap
            $breaks = $breaksByEmployee[$empId] ?? [];
            foreach ($breaks as $brk) {
                if ($brk['day_of_week'] !== null && $brk['day_of_week'] !== $enumDow) {
                    continue;
                }
                if ($brk['start'] <= $slotStartTime && $slotStartTime < $brk['end']) {
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
                'name'        => $member->user
                    ? trim($member->user->first_name . ' ' . $member->user->last_name)
                    : null,
                'photoUrl'    => $member->profile_photo,
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
