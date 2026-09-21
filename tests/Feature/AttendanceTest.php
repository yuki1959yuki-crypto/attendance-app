<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_view_attendance_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewIs('user.attendance-register');
        $response->assertViewHas(['user', 'attendance', 'formattedDate', 'formattedTime']);
    }

    /** @test */
    public function user_can_clock_in()
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $user = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => $today,
        ]);
    }

    /** @test */
    public function user_can_take_a_break_and_return_from_break()
    {
        $user = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');

        // 事ちに出勤状態を作成しておく
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in_time' => Carbon::now()->subHours(2),
        ]);

        // 1. 休憩開始（break_in）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);
        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
        ]);

        // 休憩レコードを取得
        $breakRecord = BreakRecord::where('attendance_record_id', $attendance->id)->first();
        $this->assertNotNull($breakRecord->break_in_time);
        $this->assertNull($breakRecord->break_out_time);

        // 2. 休憩終了（break_out）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);
        $response->assertRedirect(route('attendance.index'));

        $breakRecord->refresh();
        $this->assertNotNull($breakRecord->break_out_time);
    }

    /** @test */
    public function user_can_clock_out()
    {
        $user = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');

        // 事ちに出勤状態を作成しておく
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in_time' => Carbon::now()->subHours(8),
        ]);

        // 退勤（clock_out）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response->assertRedirect(route('attendance.index'));

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out_time);
    }

    /** @test */
    public function user_can_view_attendance_list()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertViewIs('user.user-attendance-list');
        $response->assertViewHas('formattedAttendanceRecords');
    }

    /** @test */
    public function user_can_view_attendance_detail()
    {
        $user = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in_time' => $today.' 09:00:00', // ← 日付を結合する
        ]);

        $response = $this->actingAs($user)->get("/attendance/{$attendance->id}");

        $response->assertStatus(200);
        $response->assertViewIs('user.user-detail');
        $response->assertViewHas('data');
    }

    /** @test */
    public function user_can_submit_stamp_correction_request()
    {
        $user = User::factory()->create();
        $today = Carbon::today()->format('Y-m-d');

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in_time' => $today.' 09:00:00',  // ← 日付を結合する
            'clock_out_time' => $today.' 18:00:00', // ← 日付を結合する
        ]);

        // 修正申請の送信（POST /attendance/{id}）
        $response = $this->actingAs($user)->post("/attendance/{$attendance->id}", [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'comment' => '電車遅延のため修正お願いします',
        ]);

        $response->assertRedirect(route('attendance.show', $attendance->id));

        // stamp_correction_requests テーブルにデータが保存されているか確認
        $this->assertDatabaseHas('stamp_correction_requests', [
            'user_id' => $user->id,
            'attendance_record_id' => $attendance->id,
            'comment' => '電車遅延のため修正お願いします',
            'status' => 0, // 承認待ち
        ]);
    }
}
