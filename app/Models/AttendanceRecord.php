<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in_time',
        'clock_out_time',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breakRecords()
    {
        return $this->hasMany(BreakRecord::class);
    }

    public function stampCorrectionRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }

    public function getTotalBreakTimeAttribute()
    {
        $totalMinutes = 0;

        foreach ($this->breakRecords as $break) {
            if ($break->break_in_time && $break->break_out_time) {
                $in = Carbon::parse($break->break_in_time);
                $out = Carbon::parse($break->break_out_time);
                $totalMinutes += $in->diffInMinutes($out);
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    // ==========================================
    // ▼ 【追加】総労働時間（休憩時間を引いた実労働時間）を算出するアクセサ
    // ==========================================
    public function getTotalWorkingTimeAttribute()
    {
        if (! $this->clock_in_time || ! $this->clock_out_time) {
            return '';
        }

        $clockIn = Carbon::parse($this->clock_in_time);
        $clockOut = Carbon::parse($this->clock_out_time);

        // 全拘束時間（分）
        $totalWorkMinutes = $clockIn->diffInMinutes($clockOut);

        // 休憩合計時間（分）を計算
        $totalBreakMinutes = 0;
        foreach ($this->breakRecords as $break) {
            if ($break->break_in_time && $break->break_out_time) {
                $totalBreakMinutes += Carbon::parse($break->break_in_time)->diffInMinutes(Carbon::parse($break->break_out_time));
            }
        }

        // 実労働時間 ＝ 全拘束時間 － 休憩合計時間（マイナスにならないよう調整）
        $netMinutes = max(0, $totalWorkMinutes - $totalBreakMinutes);

        $hours = floor($netMinutes / 60);
        $minutes = $netMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
