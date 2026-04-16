<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Http\Requests\Company\GetSlotsRequest;
use App\Http\Requests\Company\ListCompaniesRequest;
use App\Http\Resources\CompanyDetailResource;
use App\Http\Resources\CompanyResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * GET /api/companies
     *
     * Returns a paginated list of companies.
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
        $query = Company::query();

        // --- Search: name or address LIKE ---
        if ($search = $request->validated('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('address', 'LIKE', '%' . $search . '%');
            });
        }

        // --- City filter (case-insensitive exact match) ---
        if ($city = $request->validated('city')) {
            $query->whereRaw('LOWER(city) = ?', [mb_strtolower($city)]);
        }

        // --- Gender filter ---
        // A salon with gender "both" is always included regardless of the filter.
        if ($gender = $request->validated('gender')) {
            $query->where(function ($q) use ($gender): void {
                $q->where('gender', $gender)
                  ->orWhere('gender', 'both');
            });
        }

        // --- date param is accepted/validated but slot filtering happens later ---

        $companies = $query
            ->orderByDesc('rating')
            ->orderBy('name')
            ->paginate(self::PER_PAGE);

        return CompanyResource::collection($companies);
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
    public function show(int $id): CompanyDetailResource|JsonResponse
    {
        $company = Company::with([
            'openingHours',
            'galleryImages',
            'serviceCategories.services',
            'members.user',
        ])->find($id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        return new CompanyDetailResource($company);
    }

    /**
     * GET /api/companies/{id}/slots
     *
     * Returns available 30-minute time slots for the given date.
     *
     * Slots are derived from the company's opening hours for that day of week.
     * Rules:
     *   - Closed days → empty array
     *   - Slots in the past are skipped when the requested date is today
     *   - With employee_id: a slot is blocked only when THAT employee is booked
     *   - Without employee_id ("sans préférence"): a slot is blocked only when
     *     ALL active employees of the company are booked at that time. If at least
     *     one employee is free, the slot remains available.
     *
     * The Flutter app sends:
     *   - date        — required, Y-m-d
     *   - employee_id — optional, company_user.id
     *   - service_id  — optional, reserved for future slot-length logic
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

        $date = Carbon::createFromFormat('Y-m-d', $request->validated('date'))->startOfDay();

        // Map Carbon dayOfWeek (0=Sun … 6=Sat) to our enum (0=Mon … 6=Sun)
        // Carbon: 0=Sunday, 1=Monday … 6=Saturday
        // DayOfWeek: 0=Monday … 6=Sunday
        $carbonDow = (int) $date->dayOfWeek;
        $enumDow   = $carbonDow === 0 ? 6 : $carbonDow - 1;

        $openingHour = $company->openingHours
            ->first(fn ($oh) => (
                ($oh->day_of_week instanceof DayOfWeek
                    ? $oh->day_of_week->value
                    : (int) $oh->day_of_week
                ) === $enumDow
            ));

        // No record for this day, or explicitly closed
        if (! $openingHour || $openingHour->is_closed || ! $openingHour->open_time || ! $openingHour->close_time) {
            return response()->json(['data' => []]);
        }

        $openTime  = Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' ' . $openingHour->open_time);
        $closeTime = Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' ' . $openingHour->close_time);
        $now       = Carbon::now();

        $employeeId = $request->validated('employee_id');

        // Build the booked-slots structure depending on whether a specific employee
        // was requested.
        //
        // With employee_id → flat list of that employee's booked windows.
        // Without employee_id → map of employeeId → list of booked windows so we
        //   can check per-employee availability and only block a slot when every
        //   active employee is occupied.
        if ($employeeId) {
            // --- Specific employee: flat list of booked windows ---
            $bookedSlots = Appointment::query()
                ->where('company_id', $id)
                ->where('company_user_id', $employeeId)
                ->whereDate('date', $date->format('Y-m-d'))
                ->whereIn('status', [
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::Pending->value,
                ])
                ->get(['start_time', 'end_time'])
                ->map(fn ($a) => ['start' => $a->start_time, 'end' => $a->end_time])
                ->toArray();

            $totalEmployees  = null; // not used in this branch
            $perEmployeeMap  = null;
        } else {
            // --- "Sans préférence": per-employee booked windows ---
            $activeEmployeeIds = $company->members
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            $totalEmployees = count($activeEmployeeIds);

            // Group booked windows by company_user_id in a single query.
            $perEmployeeMap = [];
            if ($totalEmployees > 0) {
                $rows = Appointment::query()
                    ->whereIn('company_user_id', $activeEmployeeIds)
                    ->whereDate('date', $date->format('Y-m-d'))
                    ->whereIn('status', [
                        AppointmentStatus::Confirmed->value,
                        AppointmentStatus::Pending->value,
                    ])
                    ->get(['company_user_id', 'start_time', 'end_time']);

                foreach ($rows as $row) {
                    $perEmployeeMap[$row->company_user_id][] = [
                        'start' => $row->start_time,
                        'end'   => $row->end_time,
                    ];
                }
            }

            $bookedSlots = null; // not used in this branch
        }

        $slots  = [];
        $cursor = $openTime->copy();

        while ($cursor->lt($closeTime)) {
            // Skip slots already in the past (only relevant when date is today)
            if ($cursor->gt($now)) {
                $slotTime = $cursor->format('H:i:s');

                if ($employeeId) {
                    // Slot is blocked if THIS employee is booked at this time.
                    // Overlap: start_time <= slot < end_time
                    $isBlocked = false;
                    foreach ($bookedSlots as $booked) {
                        if ($slotTime >= $booked['start'] && $slotTime < $booked['end']) {
                            $isBlocked = true;
                            break;
                        }
                    }
                } else {
                    // Slot is blocked only when ALL active employees are occupied.
                    // We count how many employees have a booking covering this slot;
                    // if that count equals the total, every employee is busy.
                    $blockedCount = 0;
                    foreach ($perEmployeeMap as $windows) {
                        foreach ($windows as $booked) {
                            if ($slotTime >= $booked['start'] && $slotTime < $booked['end']) {
                                $blockedCount++;
                                break; // one match per employee is enough
                            }
                        }
                    }
                    $isBlocked = $totalEmployees > 0 && $blockedCount >= $totalEmployees;
                }

                if (! $isBlocked) {
                    $slots[] = [
                        'dateTime'   => $cursor->format('Y-m-d\TH:i:s'),
                        'employeeId' => $employeeId ? (string) $employeeId : null,
                    ];
                }
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
        $company = Company::with(['members.user'])->find($id);

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
