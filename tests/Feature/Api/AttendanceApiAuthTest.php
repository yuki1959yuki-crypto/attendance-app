<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 未認証時に書き込み系apiにアクセスすると401が返る()
    {
        $response = $this->postJson('/api/v1/attendance-records', []);
        $response->assertStatus(401);
    }

    /** @test */
    public function 他ユーザーの勤怠を更新しようとすると403が返る()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
            'date' => '2026-05-01',
            'clock_in_time' => '2026-05-01 09:00:00',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'user_id' => $owner->id,
            'date' => '2026-05-01',
            'clock_in_time' => '2026-05-01 08:30:00',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function 他ユーザーの勤怠を削除しようとすると403が返る()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertStatus(403);
    }
}
