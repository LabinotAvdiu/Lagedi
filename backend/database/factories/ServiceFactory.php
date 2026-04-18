<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'category_id' => null,
            'name'        => fake()->words(2, true),
            'price'       => fake()->randomFloat(2, 5, 100),
            'duration'    => fake()->randomElement([30, 45, 60]),
            'is_active'   => true,
        ];
    }
}
