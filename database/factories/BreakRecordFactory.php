<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreakRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'break_in_time' => fake()->datetimeBetween('-1 month', 'now'),
            'break_out_time' => fake()->datetimeBetween('-1 month', 'now'),
        ];
    }
}
