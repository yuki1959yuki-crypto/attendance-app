<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->date(),
            'clock_in_time' => fake()->datetimeBetween('-1 month', 'now'),
            'clock_out_time' => fake()->datetimeBetween('-1 month', 'now'),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
