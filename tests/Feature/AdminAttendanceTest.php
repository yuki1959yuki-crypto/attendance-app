<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_admin_attendance_list()
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_view_staff_monthly_attendance()
    {
        $admin = User::factory()->create(['admin_status' => 1]);
        $targetUser = User::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/staff/attendance/{$targetUser->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_approve_stamp_correction_request()
    {
        $admin = User::factory()->create(['admin_status' => 1]);
        $user = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in_time' => $today.' 09:00:00',
        ]);

        $correctionRequest = StampCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $attendance->id,
            'clock_in_time' => $today.' 09:30:00',
            'comment' => '修正テスト用の理由です',
            'status' => 0,
        ]);

        $response = $this->actingAs($admin)->post("/admin/stamp_correction_request/approve/{$correctionRequest->id}");

        $response->assertStatus(302);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $correctionRequest->id,
            'status' => 1,
        ]);
    }
}
