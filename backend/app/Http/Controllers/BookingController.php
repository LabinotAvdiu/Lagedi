<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\MyAppointmentResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeBreak;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * GET /api/bookings
     *
     * Returns the authenticated user's appointments, newest first.
     *
     * Relations eager-loaded:
     *   - company          → companyName
     *   - service          → serviceName, durationMinutes, price
     *   - companyUser.user → employeeName (first_name + last_name)
     */
    public function index(): AnonymousResourceCollection
    {
        $appointments = Appointment::query()
            ->where('user_id', auth()->id())
            ->with(['company', 'service', 'companyUser.user'])
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        return MyAppointmentResource::collection($appointments);
    }

    /**
     * POST /api/bookings
     *
     * Creates a new booking (appointment) for the authenticated user.
     *
     * The Flutter app sends:
     *   - company_id  — required
     *   - service_id  — required (used to compute end_time via service.duration)
     *   - employee_id — nullable; null means "sans préférence" → auto-assign
     *   - date_time   — ISO datetime e.g. "2026-04-16T10:00:00"
     *
     * The appointments table stores date + start_time + end_time separately,
     * and uses company_user_id (not a raw employee_id) for the FK.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $service = Service::findOrFail((int) $validated['service_id']);

        $start = Carbon::createFromFormat('Y-m-d\TH:i:s', $validated['date_time']);
        $end   = $start->copy()->addMinutes((int) $service->duration);

        $date      = $start->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime   = $end->format('H:i:s');
        $companyId = (int) $validated['company_id'];

        // Everything runs inside a transaction so the FOR UPDATE lock is held
        // until the INSERT completes — preventing two users from booking the
        // same slot simultaneously.
        $serviceId = (int) $validated['service_id'];

        // Compute the enum day-of-week value (Mon=0 … Sun=6) for the slot date
        // so we can match against employee_breaks.day_of_week.
        $slotCarbon    = Carbon::createFromFormat('Y-m-d', $date);
        $slotCarbonDow = (int) $slotCarbon->dayOfWeek;           // Carbon: Sun=0
        $slotEnumDow   = $slotCarbonDow === 0 ? 6 : $slotCarbonDow - 1; // DayOfWeek enum

        $result = DB::transaction(function () use ($request, $validated, $companyId, $serviceId, $date, $startTime, $endTime, $slotEnumDow) {
            if (isset($validated['employee_id'])) {
                $companyUserId = (int) $validated['employee_id'];

                // Lock overlapping rows for THIS employee and verify availability
                $conflict = Appointment::query()
                    ->where('company_user_id', $companyUserId)
                    ->whereDate('date', $date)
                    ->whereIn('status', [
                        AppointmentStatus::Confirmed->value,
                        AppointmentStatus::Pending->value,
                    ])
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    return ['error' => 'conflict']; // slot taken
                }

                // Reject the booking when the selected time falls within the
                // employee's break. A break applies when its day_of_week is
                // null (every day) or matches the slot's day of week, and the
                // slot start falls within [break.start_time, break.end_time).
                $onBreak = EmployeeBreak::where('company_user_id', $companyUserId)
                    ->where(function ($q) use ($slotEnumDow) {
                        $q->whereNull('day_of_week')
                          ->orWhere('day_of_week', $slotEnumDow);
                    })
                    ->where('start_time', '<=', $startTime)
                    ->where('end_time', '>', $startTime)
                    ->exists();

                if ($onBreak) {
                    return ['error' => 'break'];
                }
            } else {
                $companyUserId = $this->resolveAvailableEmployee(
                    companyId:   $companyId,
                    serviceId:   $serviceId,
                    date:        $date,
                    startTime:   $startTime,
                    endTime:     $endTime,
                    slotEnumDow: $slotEnumDow,
                );

                if ($companyUserId === null) {
                    return ['error' => 'conflict']; // no employee free
                }
            }

            return Appointment::create([
                'user_id'         => $request->user()->id,
                'company_id'      => $companyId,
                'service_id'      => (int) $validated['service_id'],
                'company_user_id' => $companyUserId,
                'date'            => $date,
                'start_time'      => $startTime,
                'end_time'        => $endTime,
                'status'          => AppointmentStatus::Confirmed,
            ]);
        });

        if (is_array($result)) {
            if (($result['error'] ?? null) === 'break') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce créneau est pendant une pause de l\'employé.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Ce créneau vient d\'être réservé par un autre utilisateur. Veuillez en choisir un autre.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data'    => new AppointmentResource($result),
        ], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:confirmed,cancelled,completed'],
        ]);

        $user = $request->user();

        $appointment = Appointment::with(['company.members'])->find($id);

        if (! $appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        $isClient = $appointment->user_id === $user->id;
        $isEmployee = $appointment->company?->members
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->isNotEmpty();

        if (! $isClient && ! $isEmployee) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $newStatus = AppointmentStatus::from($request->input('status'));
        $appointment->status = $newStatus;
        $appointment->save();

        return response()->json([
            'success' => true,
            'data'    => new AppointmentResource($appointment),
        ]);
    }

    /**
     * Find the first active employee of the given company who:
     *   1. Is linked to the given service via employee_service (with fallback to
     *      all active employees when no pivot rows exist for that service).
     *   2. Has no confirmed or pending appointment overlapping [startTime, endTime)
     *      on the given date.
     *   3. Has no break covering the slot start time on the given day of week.
     *   4. Has a custom schedule that marks them as working on that day (or no
     *      custom schedule, meaning they follow company hours).
     *
     * Uses SELECT ... FOR UPDATE to lock overlapping appointment rows so that
     * a concurrent transaction cannot book the same employee simultaneously.
     * Must be called within a DB::transaction().
     *
     * @param int $slotEnumDow  DayOfWeek enum value for the slot date (Mon=0 … Sun=6)
     */
    private function resolveAvailableEmployee(
        int    $companyId,
        int    $serviceId,
        string $date,
        string $startTime,
        string $endTime,
        int    $slotEnumDow,
    ): ?int {
        $company = Company::with('members')->find($companyId);

        if (! $company) {
            return null;
        }

        $allActiveIds = $company->members
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($allActiveIds)) {
            return null;
        }

        // Prefer employees linked to this service; fall back to all active
        $linkedIds = CompanyUser::whereIn('id', $allActiveIds)
            ->whereHas('services', fn ($q) => $q->where('services.id', $serviceId))
            ->pluck('id')
            ->all();

        $candidateIds = ! empty($linkedIds) ? $linkedIds : $allActiveIds;

        // Employees with overlapping bookings (locked for the transaction)
        $bookedEmployeeIds = Appointment::query()
            ->whereIn('company_user_id', $candidateIds)
            ->whereDate('date', $date)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate()
            ->pluck('company_user_id')
            ->unique()
            ->all();

        // Employees whose break covers the slot start time on this day
        $onBreakIds = EmployeeBreak::whereIn('company_user_id', $candidateIds)
            ->where(function ($q) use ($slotEnumDow) {
                $q->whereNull('day_of_week')
                  ->orWhere('day_of_week', $slotEnumDow);
            })
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>', $startTime)
            ->pluck('company_user_id')
            ->unique()
            ->all();

        // Employees whose custom schedule marks them as not working today
        $notWorkingIds = EmployeeSchedule::whereIn('company_user_id', $candidateIds)
            ->where('day_of_week', $slotEnumDow)
            ->where('is_working', false)
            ->pluck('company_user_id')
            ->unique()
            ->all();

        foreach ($candidateIds as $employeeId) {
            if (
                ! in_array($employeeId, $bookedEmployeeIds, true)
                && ! in_array($employeeId, $onBreakIds, true)
                && ! in_array($employeeId, $notWorkingIds, true)
            ) {
                return $employeeId;
            }
        }

        return null;
    }
}
