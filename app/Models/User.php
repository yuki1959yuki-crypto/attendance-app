<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function stampCorrectionRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }

    /**
     * attendance_status 属性のアクセサ
     * （Bladeの `$user->attendance_status` から自動呼び出しされます）
     */
    public function getAttendanceStatusAttribute()
    {
        $today = Carbon::today()->format('Y-m-d');

        $attendance = $this->attendanceRecords()
            ->whereDate('date', $today)
            ->first();

        $status = '勤務外';

        if ($attendance) {
            if ($attendance->clock_in_time && ! $attendance->clock_out_time) {
                $isOnBreak = $attendance->breakRecords()
                    ->whereNull('break_out_time')
                    ->exists();

                if ($isOnBreak) {
                    $status = '休憩中';
                } else {
                    $status = '出勤中';
                }
            } elseif ($attendance->clock_in_time && $attendance->clock_out_time) {
                $status = '退勤済';
            }
        }

        return $status;
    }
}
