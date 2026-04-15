<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
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

        // Resolve service duration to compute end_time
        $service = Service::findOrFail((int) $validated['service_id']);

        $start = Carbon::createFromFormat('Y-m-d\TH:i:s', $validated['date_time']);

        // duration column stores minutes (integer)
        $end = $start->copy()->addMinutes((int) $service->duration);

        // Resolve company_user_id:
        // - If employee_id is provided, use it directly.
        // - If null ("sans préférence"), find the first active employee of the company
        //   who is NOT already booked during the requested time window.
        if (isset($validated['employee_id'])) {
            $companyUserId = (int) $validated['employee_id'];
        } else {
            $companyUserId = $this->resolveAvailableEmployee(
                companyId:  (int) $validated['company_id'],
                date:       $start->toDateString(),
                startTime:  $start->format('H:i:s'),
                endTime:    $end->format('H:i:s'),
            );

            if ($companyUserId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun employé disponible sur ce créneau',
                ], 422);
            }
        }

        $appointment = Appointment::create([
            'user_id'         => $request->user()->id,
            'company_id'      => (int) $validated['company_id'],
            'service_id'      => (int) $validated['service_id'],
            'company_user_id' => $companyUserId,
            'date'            => $start->toDateString(),
            'start_time'      => $start->format('H:i:s'),
            'end_time'        => $end->format('H:i:s'),
            'status'          => AppointmentStatus::Confirmed,
        ]);

        return response()->json([
            'success' => true,
            'data'    => new AppointmentResource($appointment),
        ], 201);
    }

    /**
     * Find the first active employee of the given company who has no confirmed
     * or pending appointment that overlaps [startTime, endTime) on the given date.
     *
     * Overlap condition: an existing appointment overlaps the requested window when
     *   existing.start_time < requested.end_time
     *   AND existing.end_time > requested.start_time
     *
     * Returns the company_user.id of the first free employee, or null if all are booked.
     */
    private function resolveAvailableEmployee(
        int $companyId,
        string $date,
        string $startTime,
        string $endTime,
    ): ?int {
        $company = Company::with('members')->find($companyId);

        if (! $company) {
            return null;
        }

        $activeEmployeeIds = $company->members
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($activeEmployeeIds)) {
            return null;
        }

        // IDs of employees who already have an overlapping appointment on this slot.
        $bookedEmployeeIds = Appointment::query()
            ->whereIn('company_user_id', $activeEmployeeIds)
            ->whereDate('date', $date)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
            ])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->pluck('company_user_id')
            ->unique()
            ->all();

        // Return the first active employee who is not in the booked list.
        foreach ($activeEmployeeIds as $employeeId) {
            if (! in_array($employeeId, $bookedEmployeeIds, true)) {
                return $employeeId;
            }
        }

        return null;
    }
}
