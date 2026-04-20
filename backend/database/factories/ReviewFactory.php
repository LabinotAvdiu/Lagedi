<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'user_id'        => User::factory(),
            'company_id'     => Company::factory(),
            'rating'         => $this->faker->numberBetween(1, 5),
            'comment'        => $this->faker->optional()->sentence(),
            'status'         => 'visible',
        ];
    }
}
