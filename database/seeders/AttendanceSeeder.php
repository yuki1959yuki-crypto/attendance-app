<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();

        for ($i = 5; $i >= 1; $i--) {
            $targetMonth = Carbon::now()->subMonths($i);
            $daysCreated = 0;
            $day = 1;
            while ($daysCreated < 15 && $day <= 31) {
                $date = $targetMonth->copy()->day($day);
                if ($date->isWeekday()) {
                    $this->createAttendanceWithBreak(
                        $user1->id,
                        $date->format('Y-m-d'),
                        '09:00:00',
                        '18:00:00'
                    );
                    $daysCreated++;
                }
                $day++;
            }
        }

        $currentMonth = Carbon::now();
        $patterns = array_merge(
            array_fill(0, 10, ['09:00:00', '18:00:00']),
            array_fill(0, 3, ['09:00:00', '20:00:00']),
            array_fill(0, 2, ['09:30:00', '18:00:00']),
            array_fill(0, 1, ['09:00:00', '17:00:00']),
            array_fill(0, 1, ['08:00:00', '21:00:00'])
        );

        foreach ($patterns as $index => $time) {
            $date = $currentMonth->copy()->day($index + 1);

            if ($date->isToday() || $date->isFuture()) {
                continue;
            }

            $this->createAttendanceWithBreak(
                $user1->id,
                $date->format('Y-m-d'),
                $time[0],
                $time[1]
            );
        }

        AttendanceRecord::factory()->count(10)->for($user2)->has(BreakRecord::factory()->count(1), 'breakRecords')->create();
    }

    private function createAttendanceWithBreak($userId, $date, $clockIn, $clockOut)
    {
        $attendance = AttendanceRecord::create([
            'user_id' => $userId,
            'date' => $date,
            'clock_in_time' => "{$date} {$clockIn}",
            'clock_out_time' => "{$date} {$clockOut}",
        ]);

        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_in_time' => "{$date} 12:00:00",
            'break_out_time' => "{$date} 13:00:00",
        ]);
    }
}
