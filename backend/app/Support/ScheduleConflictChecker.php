<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Centralises "are there appointments blocking this break / day-off ?" checks.
 *
 * Only live statuses count as conflicts : pending, confirmed (walk-ins are
 * stored as confirmed). Cancelled and rejected are inert — the slot is free.
 *
 * Used by both employee schedule (MyScheduleController) and company schedule
 * (MyCompanyController) so the rules stay identical whatever the salon mode.
 */
final class ScheduleConflictChecker
{
    /** Statuses that count as occupying a time slot. */
    private const LIVE_STATUSES = [
        // Walk-ins are stored as `confirmed` + `is_walk_in=true` so they
        // are already covered by the confirmed bucket.
    ];

    public static function liveStatuses(): array
    {
        return [
            AppointmentStatus::Pending->value,
            AppointmentStatus::Confirmed->value,
        ];
    }

    /**
     * Live appointments overlapping [startDate, endDate] for the given scope.
     * - Employee scope (companyUserId set) → only their appointments.
     * - Company scope (companyUserId null) → all appointments of the company.
     *
     * @return Collection<int, Appointment>
     */
    public static function appointmentsInDateRange(
        int $companyId,
        ?int $companyUserId,
        string $startDate, // Y-m-d
        string $endDate,   // Y-m-d
    ): Collection {
        $query = Appointment::where('company_id', $companyId)
            ->whereIn('status', self::liveStatuses())
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['service', 'user']);

        if ($companyUserId !== null) {
            $query->where('company_user_id', $companyUserId);
        }

        return $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Live appointments whose START falls in [breakStart, breakEnd) on the
     * break's day-of-week (or every day when $dayOfWeek is null). Restricted
     * to future occurrences so we don't flag historical bookings.
     *
     * Backend enum dayOfWeek: 0=Mon … 6=Sun. Carbon::dayOfWeek: 0=Sun … 6=Sat.
     *
     * @param  int|null  $dayOfWeek  0..6 enum, null = every day
     * @param  string    $breakStart "HH:MM" or "HH:MM:SS"
     * @param  string    $breakEnd   "HH:MM" or "HH:MM:SS"
     * @param  int       $horizonDays  how far into the future to look
     * @return Collection<int, Appointment>
     */
    public static function appointmentsInBreakWindow(
        int     $companyId,
        ?int    $companyUserId,
        ?int    $dayOfWeek,
        string  $breakStart,
        string  $breakEnd,
        int     $horizonDays = 90,
    ): Collection {
        $from = Carbon::today()->format('Y-m-d');
        $to   = Carbon::today()->addDays($horizonDays)->format('Y-m-d');

        // Normalise to HH:MM:SS for consistent comparison.
        $start = strlen($breakStart) === 5 ? $breakStart . ':00' : $breakStart;
        $end   = strlen($breakEnd)   === 5 ? $breakEnd   . ':00' : $breakEnd;

        $query = Appointment::where('company_id', $companyId)
            ->whereIn('status', self::liveStatuses())
            ->whereBetween('date', [$from, $to])
            // "Starts during" semantic — the user said: RDV qui commencent pendant la pause.
            ->where('start_time', '>=', $start)
            ->where('start_time', '<', $end)
            ->with(['service', 'user']);

        if ($companyUserId !== null) {
            $query->where('company_user_id', $companyUserId);
        }

        $candidates = $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        if ($dayOfWeek === null) {
            return $candidates;
        }

        // Filter to the specific weekday — enum 0=Mon … 6=Sun vs Carbon.
        return $candidates->filter(function (Appointment $a) use ($dayOfWeek) {
            $cDow = $a->date instanceof Carbon
                ? (int) $a->date->dayOfWeek
                : (int) Carbon::parse((string) $a->date)->dayOfWeek;
            $enumDow = $cDow === 0 ? 6 : $cDow - 1;
            return $enumDow === $dayOfWeek;
        })->values();
    }

    /**
     * Serialise an appointment collection for the 409 conflict response.
     * Keeps the payload compact — the UI only needs to show a readable list.
     */
    public static function toConflictPayload(Collection $appointments): array
    {
        return $appointments->map(function (Appointment $a) {
            $firstName = $a->is_walk_in
                ? $a->walk_in_first_name
                : $a->user?->first_name;
            $lastName = $a->is_walk_in
                ? $a->walk_in_last_name
                : $a->user?->last_name;

            return [
                'id'              => (string) $a->id,
                'date'            => $a->date instanceof Carbon
                    ? $a->date->toDateString()
                    : substr((string) $a->date, 0, 10),
                'startTime'       => substr((string) $a->start_time, 0, 5),
                'endTime'         => substr((string) $a->end_time, 0, 5),
                'clientFirstName' => NameFormatter::titleCase($firstName),
                'clientLastName'  => NameFormatter::titleCase($lastName),
                'isWalkIn'        => (bool) $a->is_walk_in,
                'serviceName'     => $a->service?->name,
            ];
        })->all();
    }
}
