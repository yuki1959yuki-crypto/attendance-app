<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StampCorrectionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'user_id' => User::factory(),
            'clock_in_time' => fake()->datetimeBetween('-1 month', 'now'),
            'clock_out_time' => fake()->datetimeBetween('-1 month', 'now'),
            'comment' => fake()->sentence(),
            'status' => 0,
        ];
    }
}
