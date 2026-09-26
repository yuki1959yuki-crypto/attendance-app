<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ゲストはレポートページにアクセスできない()
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function メール認証済みユーザーはレポートページにアクセスできる()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in_time' => '2026-05-01 09:00:00',
            'clock_out_time' => '2026-05-01 18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);
    }

    /** @test */
    public function 勤怠記録がないユーザーでもレポートページにアクセスできる()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);
    }
}
