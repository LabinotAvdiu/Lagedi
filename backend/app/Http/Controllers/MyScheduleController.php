<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Http\Requests\MySchedule\GetScheduleRequest;
use App\Http\Requests\MySchedule\StoreBreakRequest;
use App\Http\Requests\MySchedule\StoreDayOffRequest;
use App\Http\Requests\MySchedule\StoreWalkInRequest;
use App\Http\Requests\MySchedule\UpdateEmployeeHoursRequest;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeBreak;
use App\Models\EmployeeDayOff;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

class MyScheduleController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /api/my-schedule
    // -------------------------------------------------------------------------

    /**
     * Returns the authenticated employee's full day schedule with 30-min slots.
     *
     * Slot statuses:
     *   - "free"   → no appointment, not a break
     *   - "booked" → an appointment (regular or walk-in) occupies that slot
     *   - "break"  → falls inside an employee break window
     */
    public function show(GetScheduleRequest $request): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $date = $request->input('date')
            ? Carbon::createFromFormat('Y-m-d', $request->input('date'))->startOfDay()
            : Carbon::today();

        return response()->json([
            'success' => true,
            'data'    => $this->buildSchedulePayload($companyUser, $date),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/my-schedule/walk-in
    // -------------------------------------------------------------------------

    /**
     * Creates a walk-in appointment for the authenticated employee and returns
     * the refreshed day schedule.
     */
    public function storeWalkIn(StoreWalkInRequest $request): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $validated = $request->validated();

        $service = Service::findOrFail((int) $validated['service_id']);

        $start     = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['time']);
        $end       = $start->copy()->addMinutes((int) $service->duration);
        $date      = $start->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime   = $end->format('H:i:s');

        // Verify slot is free before inserting
        $conflict = Appointment::query()
            ->where('company_user_id', $companyUser->id)
            ->whereDate('date', $date)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Ce créneau est déjà occupé.',
            ], 409);
        }

        Appointment::create([
            'company_id'         => $companyUser->company_id,
            'company_user_id'    => $companyUser->id,
            'service_id'         => (int) $validated['service_id'],
            'user_id'            => null,
            'date'               => $date,
            'start_time'         => $startTime,
            'end_time'           => $endTime,
            'status'             => AppointmentStatus::Confirmed,
            'is_walk_in'         => true,
            'walk_in_first_name' => $validated['first_name'],
            'walk_in_last_name'  => $validated['last_name'] ?? null,
            'walk_in_phone'      => $validated['phone'] ?? null,
        ]);

        $dateCarbon = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();

        return response()->json([
            'success' => true,
            'data'    => $this->buildSchedulePayload($companyUser, $dateCarbon),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/my-schedule/upcoming
    // -------------------------------------------------------------------------

    /**
     * Returns the employee's next upcoming appointment (the closest future one).
     */
    public function upcoming(): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $now = Carbon::now();

        /** @var Appointment|null $appointment */
        $appointment = Appointment::query()
            ->where('company_user_id', $companyUser->id)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ])
            ->where(function ($query) use ($now) {
                $query->whereDate('date', '>', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->whereDate('date', $now->toDateString())
                          ->where('start_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->with(['user', 'service'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        if (! $appointment) {
            return response()->json([
                'success' => true,
                'data'    => null,
                'message' => 'No upcoming appointments.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatAppointmentSlot($appointment),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/my-schedule/settings
    // -------------------------------------------------------------------------

    /**
     * Returns the employee's work schedule settings:
     * company opening hours (for reference), custom employee hours, breaks, days off.
     */
    public function settings(): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $companyUser->load(['company.openingHours', 'schedules', 'breaks', 'daysOff']);

        $companyHours = $companyUser->company->openingHours
            ->sortBy('day_of_week')
            ->map(fn ($oh) => [
                'dayOfWeek' => $oh->day_of_week instanceof DayOfWeek
                    ? $oh->day_of_week->value
                    : (int) $oh->day_of_week,
                'openTime'  => $oh->open_time ? substr($oh->open_time, 0, 5) : null,
                'closeTime' => $oh->close_time ? substr($oh->close_time, 0, 5) : null,
                'isClosed'  => (bool) $oh->is_closed,
            ])
            ->values();

        $employeeHours = $companyUser->schedules
            ->sortBy('day_of_week')
            ->map(fn ($s) => [
                'dayOfWeek' => $s->day_of_week instanceof DayOfWeek
                    ? $s->day_of_week->value
                    : (int) $s->day_of_week,
                'startTime' => $s->start_time ? substr($s->start_time, 0, 5) : null,
                'endTime'   => $s->end_time ? substr($s->end_time, 0, 5) : null,
                'isWorking' => (bool) $s->is_working,
            ])
            ->values();

        $breaks = $companyUser->breaks
            ->map(fn ($b) => [
                'id'         => (string) $b->id,
                'dayOfWeek'  => $b->day_of_week instanceof DayOfWeek
                    ? $b->day_of_week->value
                    : (isset($b->day_of_week) ? (int) $b->day_of_week : null),
                'startTime'  => $b->start_time ? substr($b->start_time, 0, 5) : null,
                'endTime'    => $b->end_time ? substr($b->end_time, 0, 5) : null,
                'label'      => $b->label,
            ])
            ->values();

        $daysOff = $companyUser->daysOff
            ->map(fn ($d) => [
                'id'     => (string) $d->id,
                'date'   => $d->date instanceof \Carbon\Carbon
                    ? $d->date->toDateString()
                    : (string) $d->date,
                'reason' => $d->reason,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'companyHours'  => $companyHours,
                'employeeHours' => $employeeHours,
                'breaks'        => $breaks,
                'daysOff'       => $daysOff,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PUT /api/my-schedule/hours
    // -------------------------------------------------------------------------

    /**
     * Bulk-upsert the employee's custom work hours (one record per day of week).
     * Expects exactly 7 items in the "hours" array.
     */
    public function updateHours(UpdateEmployeeHoursRequest $request): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $validated = $request->validated();

        // Load company opening hours for validation
        $companyUser->load('company.openingHours');
        $companyHours = $companyUser->company->openingHours->keyBy(fn ($oh) =>
            $oh->day_of_week instanceof DayOfWeek ? $oh->day_of_week->value : (int) $oh->day_of_week
        );

        foreach ($validated['hours'] as $item) {
            $dow = (int) $item['day_of_week'];
            $isWorking = (bool) $item['is_working'];

            if ($isWorking) {
                $companyDay = $companyHours->get($dow);

                // Cannot work on a day the salon is closed
                if (!$companyDay || $companyDay->is_closed) {
                    return response()->json([
                        'success' => false,
                        'message' => "Le salon est fermé ce jour-là (jour $dow). Impossible de travailler.",
                    ], 422);
                }

                // Employee hours must be within company hours
                $empStart = $item['start_time'] . ':00';
                $empEnd = $item['end_time'] . ':00';
                if ($empStart < $companyDay->open_time || $empEnd > $companyDay->close_time) {
                    return response()->json([
                        'success' => false,
                        'message' => "Les horaires doivent être dans les horaires du salon ("
                            . substr($companyDay->open_time, 0, 5) . " - "
                            . substr($companyDay->close_time, 0, 5) . ").",
                    ], 422);
                }
            }
        }

        foreach ($validated['hours'] as $item) {
            EmployeeSchedule::updateOrCreate(
                [
                    'company_user_id' => $companyUser->id,
                    'day_of_week'     => (int) $item['day_of_week'],
                ],
                [
                    'start_time' => $item['is_working'] ? ($item['start_time'] . ':00') : '00:00:00',
                    'end_time'   => $item['is_working'] ? ($item['end_time'] . ':00') : '00:00:00',
                    'is_working' => (bool) $item['is_working'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Work hours updated.',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/my-schedule/breaks
    // -------------------------------------------------------------------------

    public function storeBreak(StoreBreakRequest $request): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $validated = $request->validated();

        $break = EmployeeBreak::create([
            'company_user_id' => $companyUser->id,
            'day_of_week'     => isset($validated['day_of_week']) ? (int) $validated['day_of_week'] : null,
            'start_time'      => $validated['start_time'] . ':00',
            'end_time'        => $validated['end_time'] . ':00',
            'label'           => $validated['label'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => (string) $break->id,
                'dayOfWeek' => $break->day_of_week instanceof DayOfWeek
                    ? $break->day_of_week->value
                    : (($raw = $break->getRawOriginal('day_of_week')) !== null ? (int) $raw : null),
                'startTime' => substr($break->start_time, 0, 5),
                'endTime'   => substr($break->end_time, 0, 5),
                'label'     => $break->label,
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/my-schedule/breaks/{id}
    // -------------------------------------------------------------------------

    public function destroyBreak(int $id): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $break = EmployeeBreak::where('id', $id)
            ->where('company_user_id', $companyUser->id)
            ->first();

        if (! $break) {
            return response()->json([
                'success' => false,
                'message' => 'Break not found.',
            ], 404);
        }

        $break->delete();

        return response()->json(['success' => true], 204);
    }

    // -------------------------------------------------------------------------
    // POST /api/my-schedule/days-off
    // -------------------------------------------------------------------------

    public function storeDayOff(StoreDayOffRequest $request): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $validated = $request->validated();

        // Prevent duplicate date
        $exists = EmployeeDayOff::where('company_user_id', $companyUser->id)
            ->where('date', $validated['date'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A day off for this date already exists.',
            ], 409);
        }

        $dayOff = EmployeeDayOff::create([
            'company_user_id' => $companyUser->id,
            'date'            => $validated['date'],
            'reason'          => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'     => (string) $dayOff->id,
                'date'   => $dayOff->date->toDateString(),
                'reason' => $dayOff->reason,
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/my-schedule/days-off/{id}
    // -------------------------------------------------------------------------

    public function destroyDayOff(int $id): JsonResponse
    {
        $companyUser = $this->resolveEmployeeCompanyUser();

        if ($companyUser instanceof JsonResponse) {
            return $companyUser;
        }

        $dayOff = EmployeeDayOff::where('id', $id)
            ->where('company_user_id', $companyUser->id)
            ->first();

        if (! $dayOff) {
            return response()->json([
                'success' => false,
                'message' => 'Day off not found.',
            ], 404);
        }

        $dayOff->delete();

        return response()->json(['success' => true], 204);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the CompanyUser pivot for the authenticated user.
     * Returns a 403 JsonResponse if the user is not linked to any company.
     */
    private function resolveEmployeeCompanyUser(): CompanyUser|JsonResponse
    {
        $userId = auth()->id();

        $companyUser = CompanyUser::where('user_id', $userId)
            ->where('is_active', true)
            ->with(['company', 'user'])
            ->first();

        if (! $companyUser) {
            return response()->json([
                'success' => false,
                'message' => 'You are not linked to any company.',
            ], 403);
        }

        return $companyUser;
    }

    /**
     * Builds the raw schedule payload for a given employee and date.
     *
     * Priority order:
     *   1. If the date is in employee_days_off  → isDayOff=true, empty appointments.
     *   2. If the employee has a custom schedule for this day:
     *        - is_working = false → isDayOff=true (not working), empty appointments.
     *        - is_working = true  → use employee's own start/end times.
     *   3. Fall back to company opening hours.
     *   4. Attach breaks that apply to this day (day_of_week matches OR null = every day).
     *   5. Attach appointments as raw objects — no slot generation.
     */
    private function buildSchedulePayload(CompanyUser $companyUser, Carbon $date): array
    {
        $company = $companyUser->company;

        // Carbon: Mon=1, Tue=2, …, Sun=0  →  DayOfWeek enum: Mon=0 … Sun=6
        $carbonDow = $date->dayOfWeek;
        $enumValue = $carbonDow === CarbonInterface::SUNDAY ? 6 : $carbonDow - 1;

        // 1. Employee explicit day off
        $dayOff = EmployeeDayOff::where('company_user_id', $companyUser->id)
            ->where('date', $date->toDateString())
            ->first();

        if ($dayOff) {
            return $this->emptyPayload($companyUser, $company, $date, true, $dayOff->reason);
        }

        // 2. Employee custom schedule for this day
        $employeeSchedule = EmployeeSchedule::where('company_user_id', $companyUser->id)
            ->where('day_of_week', $enumValue)
            ->first();

        if ($employeeSchedule) {
            if (! $employeeSchedule->is_working) {
                return $this->emptyPayload($companyUser, $company, $date, true, null);
            }

            $openTime  = $employeeSchedule->start_time;
            $closeTime = $employeeSchedule->end_time;
        } else {
            // 3. Fallback to company opening hours
            $openingHour = $company->openingHours()
                ->where('day_of_week', $enumValue)
                ->first();

            if (! $openingHour || $openingHour->is_closed) {
                return $this->emptyPayload($companyUser, $company, $date, true, null);
            }

            $openTime  = $openingHour->open_time;
            $closeTime = $openingHour->close_time;
        }

        // 4. Breaks that apply to this day
        $breaks = EmployeeBreak::where('company_user_id', $companyUser->id)
            ->where(function ($q) use ($enumValue) {
                $q->whereNull('day_of_week')
                  ->orWhere('day_of_week', $enumValue);
            })
            ->get();

        // 5. Appointments for this date
        $appointments = Appointment::query()
            ->where('company_user_id', $companyUser->id)
            ->whereDate('date', $date->toDateString())
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ])
            ->with(['user', 'service'])
            ->orderBy('start_time')
            ->get();

        return [
            'employee'     => $this->formatEmployee($companyUser),
            'company'      => $this->formatCompany($company),
            'date'         => $date->toDateString(),
            'isDayOff'     => false,
            'dayOffReason' => null,
            'workHours'    => [
                'startTime' => substr($openTime, 0, 5),
                'endTime'   => substr($closeTime, 0, 5),
            ],
            'breaks'       => $breaks->map(fn (EmployeeBreak $b) => [
                'startTime' => substr($b->start_time, 0, 5),
                'endTime'   => substr($b->end_time, 0, 5),
                'label'     => $b->label,
            ])->values()->all(),
            'appointments' => $appointments->map(
                fn (Appointment $a) => $this->formatAppointmentSlot($a)
            )->values()->all(),
        ];
    }

    /**
     * Returns a schedule payload for a day off or a non-working day.
     */
    private function emptyPayload(
        CompanyUser $companyUser,
        Company $company,
        Carbon $date,
        bool $isDayOff,
        ?string $dayOffReason,
    ): array {
        return [
            'employee'     => $this->formatEmployee($companyUser),
            'company'      => $this->formatCompany($company),
            'date'         => $date->toDateString(),
            'isDayOff'     => $isDayOff,
            'dayOffReason' => $dayOffReason,
            'workHours'    => null,
            'breaks'       => [],
            'appointments' => [],
        ];
    }

    /**
     * Formats an Appointment model into the raw appointment shape.
     * Handles both regular (user_id set) and walk-in appointments.
     */
    private function formatAppointmentSlot(Appointment $appointment): array
    {
        $isWalkIn = (bool) $appointment->is_walk_in;

        if ($isWalkIn) {
            $clientFirstName = $appointment->walk_in_first_name;
            $clientLastName  = $appointment->walk_in_last_name;
            $clientPhone     = $appointment->walk_in_phone;
        } else {
            $clientFirstName = $appointment->user?->first_name;
            $clientLastName  = $appointment->user?->last_name;
            $clientPhone     = $appointment->user?->phone;
        }

        return [
            'id'              => (string) $appointment->id,
            'startTime'       => substr($appointment->start_time, 0, 5),
            'endTime'         => substr($appointment->end_time, 0, 5),
            'clientFirstName' => $clientFirstName,
            'clientLastName'  => $clientLastName,
            'clientPhone'     => $clientPhone,
            'serviceName'     => $appointment->service?->name,
            'durationMinutes' => $appointment->service ? (int) $appointment->service->duration : null,
            'price'           => $appointment->service ? (float) $appointment->service->price : null,
            'isWalkIn'        => $isWalkIn,
        ];
    }

    private function formatEmployee(CompanyUser $companyUser): array
    {
        return [
            'id'        => (string) $companyUser->id,
            'firstName' => $companyUser->user?->first_name,
            'lastName'  => $companyUser->user?->last_name,
        ];
    }

    private function formatCompany(Company $company): array
    {
        return [
            'id'   => (string) $company->id,
            'name' => $company->name,
        ];
    }
}
