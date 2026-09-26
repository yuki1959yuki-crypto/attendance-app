<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApiReadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠一覧が_jso_nで取得できる()
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'date',
                            'clock_in_time',
                            'clock_out_time',
                            'comment',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'links',
                    'meta',
                ],
            ]);
    }

    /** @test */
    public function 勤怠詳細が_jso_nで取得できる()
    {
        $user = User::factory()->create();
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $attendance->id);
    }

    /** @test */
    public function 存在しない_i_dの勤怠詳細にアクセスした場合は404エラーと指定の_jso_nが返る()
    {
        $response = $this->getJson('/api/v1/attendance-records/99999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => '指定された勤怠データが見つかりません。',
            ]);
    }
}
