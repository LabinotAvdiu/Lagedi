<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds a handful of realistic appointments spread across the next few days
 * so that the slots endpoint visibly excludes taken windows during manual testing.
 *
 * Also seeds Karim's daily schedule for today (2026-04-16) with 3 appointments:
 *   - 09:00 Coupe homme       (30 min) — regular client test@test.com
 *   - 11:00 Coupe + Barbe     (45 min) — walk-in Jean
 *   - 14:30 Taille de barbe   (20 min) — regular client client@test.com
 *
 * Assumptions (matching CompanySeeder):
 *   - Company 1 (Le Barbier Parisien) is open Mon–Sat 09:00–19:00
 *   - Company 1 has 3 active employees (owner + 2 employees)
 *   - There is at least one test client (test@test.com)
 */
class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'Le Barbier Parisien')->first();

        if (! $company) {
            $this->command->warn('AppointmentSeeder: Company "Le Barbier Parisien" not found — skipping.');
            return;
        }

        $client = User::where('email', 'test@test.com')->first();

        if (! $client) {
            $this->command->warn('AppointmentSeeder: Client test@test.com not found — skipping.');
            return;
        }

        $employees = CompanyUser::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $service = Service::where('company_id', $company->id)->first();

        if ($employees->isEmpty() || ! $service) {
            $this->command->warn('AppointmentSeeder: No employees or services found — skipping.');
            return;
        }

        // Find the next weekday (Mon–Sat) starting from tomorrow.
        // Company 1 is closed on Sunday (DayOfWeek::Sunday = 6, enumDow=6).
        $nextOpenDay = fn (int $daysAhead): Carbon => tap(
            Carbon::today()->addDays($daysAhead),
            function (Carbon $d): void {
                // Advance past Sunday (Carbon dayOfWeek = 0)
                while ($d->dayOfWeek === 0) {
                    $d->addDay();
                }
            }
        );

        $day1 = $nextOpenDay(1); // tomorrow (skip Sunday if needed)
        $day2 = $nextOpenDay(2);
        $day3 = $nextOpenDay(3);
        $day4 = $nextOpenDay(5); // a few days further

        $emp0 = $employees->get(0);
        $emp1 = $employees->get(1) ?? $employees->get(0);
        $emp2 = $employees->get(2) ?? $employees->get(0);

        $bookings = [
            // Day 1 — employee 0: morning block 09:00–10:00
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp0->id,
                'service_id'      => $service->id,
                'date'            => $day1->format('Y-m-d'),
                'start_time'      => '09:00:00',
                'end_time'        => '10:00:00',
                'status'          => AppointmentStatus::Confirmed,
            ],
            // Day 1 — employee 1: afternoon block 14:00–15:00
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp1->id,
                'service_id'      => $service->id,
                'date'            => $day1->format('Y-m-d'),
                'start_time'      => '14:00:00',
                'end_time'        => '15:00:00',
                'status'          => AppointmentStatus::Confirmed,
            ],
            // Day 2 — employee 2: mid-morning 10:30–11:00
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp2->id,
                'service_id'      => $service->id,
                'date'            => $day2->format('Y-m-d'),
                'start_time'      => '10:30:00',
                'end_time'        => '11:00:00',
                'status'          => AppointmentStatus::Pending,
            ],
            // Day 3 — employee 0: all three employees share the 11:00 slot
            //         → slot should be hidden in "sans préférence" mode
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp0->id,
                'service_id'      => $service->id,
                'date'            => $day3->format('Y-m-d'),
                'start_time'      => '11:00:00',
                'end_time'        => '11:30:00',
                'status'          => AppointmentStatus::Confirmed,
            ],
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp1->id,
                'service_id'      => $service->id,
                'date'            => $day3->format('Y-m-d'),
                'start_time'      => '11:00:00',
                'end_time'        => '11:30:00',
                'status'          => AppointmentStatus::Confirmed,
            ],
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp2->id,
                'service_id'      => $service->id,
                'date'            => $day3->format('Y-m-d'),
                'start_time'      => '11:00:00',
                'end_time'        => '11:30:00',
                'status'          => AppointmentStatus::Confirmed,
            ],
            // Day 4 — employee 0: late afternoon 16:00–17:00
            [
                'user_id'         => $client->id,
                'company_id'      => $company->id,
                'company_user_id' => $emp0->id,
                'service_id'      => $service->id,
                'date'            => $day4->format('Y-m-d'),
                'start_time'      => '16:00:00',
                'end_time'        => '17:00:00',
                'status'          => AppointmentStatus::Confirmed,
            ],
        ];

        foreach ($bookings as $data) {
            Appointment::create($data);
        }

        $this->command->info('AppointmentSeeder: ' . count($bookings) . ' appointments created.');

        // =====================================================================
        // Karim's daily schedule — TODAY (2026-04-16)
        // =====================================================================
        $this->seedKarimDailySchedule($company);
    }

    /**
     * Clears and re-seeds Karim's appointments for today so the daily schedule
     * screen is populated with realistic data during manual testing.
     */
    private function seedKarimDailySchedule(Company $company): void
    {
        $today = Carbon::today()->format('Y-m-d'); // 2026-04-16

        // Resolve Karim's company_user record by his known email.
        $karim = User::where('email', 'karim@barbier-parisien.fr')->first();

        if (! $karim) {
            $this->command->warn('AppointmentSeeder (Karim): User karim@barbier-parisien.fr not found — skipping daily schedule.');
            return;
        }

        $karimCu = CompanyUser::where('company_id', $company->id)
            ->where('user_id', $karim->id)
            ->first();

        if (! $karimCu) {
            $this->command->warn('AppointmentSeeder (Karim): CompanyUser record not found — skipping daily schedule.');
            return;
        }

        // Resolve services by name (safe against ID drift after re-seeds).
        $serviceCoupeHomme  = Service::where('company_id', $company->id)->where('name', 'Coupe homme')->first();
        $serviceCoupeBarbe  = Service::where('company_id', $company->id)->where('name', 'Coupe + Barbe')->first();
        $serviceTailleBarbe = Service::where('company_id', $company->id)->where('name', 'Taille de barbe')->first();

        if (! $serviceCoupeHomme || ! $serviceCoupeBarbe || ! $serviceTailleBarbe) {
            $this->command->warn('AppointmentSeeder (Karim): One or more services not found — skipping daily schedule.');
            return;
        }

        // Resolve regular clients.
        $clientTest   = User::where('email', 'test@test.com')->first();
        $clientClient = User::where('email', 'client@test.com')->first();

        if (! $clientTest || ! $clientClient) {
            $this->command->warn('AppointmentSeeder (Karim): One or more client users not found — skipping daily schedule.');
            return;
        }

        // Clear existing appointments for Karim today to avoid conflicts.
        Appointment::where('company_user_id', $karimCu->id)
            ->where('date', $today)
            ->delete();

        $this->command->info("AppointmentSeeder (Karim): Cleared existing appointments for {$today}.");

        $dailyBookings = [
            // 09:00 — Coupe homme (30 min) — regular client test@test.com
            [
                'user_id'         => $clientTest->id,
                'company_id'      => $company->id,
                'company_user_id' => $karimCu->id,
                'service_id'      => $serviceCoupeHomme->id,
                'date'            => $today,
                'start_time'      => '09:00:00',
                'end_time'        => '09:30:00', // 09:00 + 30 min
                'status'          => AppointmentStatus::Confirmed,
                'is_walk_in'      => false,
            ],
            // 11:00 — Coupe + Barbe (45 min) — walk-in Jean
            [
                'user_id'            => null,
                'company_id'         => $company->id,
                'company_user_id'    => $karimCu->id,
                'service_id'         => $serviceCoupeBarbe->id,
                'date'               => $today,
                'start_time'         => '11:00:00',
                'end_time'           => '11:45:00', // 11:00 + 45 min
                'status'             => AppointmentStatus::Confirmed,
                'is_walk_in'         => true,
                'walk_in_first_name' => 'Jean',
                'walk_in_phone'      => '+33612345678',
            ],
            // 14:30 — Taille de barbe (20 min) — regular client client@test.com
            [
                'user_id'         => $clientClient->id,
                'company_id'      => $company->id,
                'company_user_id' => $karimCu->id,
                'service_id'      => $serviceTailleBarbe->id,
                'date'            => $today,
                'start_time'      => '14:30:00',
                'end_time'        => '14:50:00', // 14:30 + 20 min
                'status'          => AppointmentStatus::Confirmed,
                'is_walk_in'      => false,
            ],
        ];

        foreach ($dailyBookings as $data) {
            Appointment::create($data);
        }

        $this->command->info('AppointmentSeeder (Karim): ' . count($dailyBookings) . ' daily appointments created for ' . $today . '.');
    }
}
