<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'user_id'    => User::factory(),
            'company_id' => $company->id,
            'service_id' => Service::factory()->create(['company_id' => $company->id])->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time'   => '10:30:00',
            'status'     => AppointmentStatus::Confirmed,
            'is_walk_in' => false,
        ];
    }
}
