<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name'         => fake()->company(),
            'city'         => fake()->city(),
            'address'      => fake()->address(),
            'phone'        => fake()->phoneNumber(),
            'booking_mode' => 'employee_based',
        ];
    }
}
