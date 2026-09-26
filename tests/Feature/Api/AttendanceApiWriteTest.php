<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiWriteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠を新規登録できる()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in_time' => '2026-05-01 09:00:00',
            'clock_out_time' => '2026-05-01 18:00:00',
            'comment' => 'テスト備考',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-05-01',
        ]);
    }

    /** @test */
    public function 不正なデータで勤怠登録した場合は422エラーが返る()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'clock_in_time']);
    }

    /** @test */
    public function 勤怠を更新できる()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in_time' => '2026-05-01 09:00:00',
        ]);

        $response = $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in_time' => '2026-05-01 08:30:00',
            'clock_out_time' => '2026-05-01 17:30:00',
            'comment' => '更新後の備考',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in_time' => '2026-05-01 08:30:00',
        ]);
    }

    /** @test */
    public function 勤怠を削除できる()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);
    }
}
