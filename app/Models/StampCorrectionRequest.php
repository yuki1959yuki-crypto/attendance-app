<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'date',
        'clock_in_time',
        'clock_out_time',
        'comment',
        'breaks',
        'status',
    ];

    public function AttendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * approval_status という名前でアクセスされたときの処理
     */
    public function getApprovalStatusAttribute()
    {
        return $this->status === 1 ? '承認済み' : '承認待ち';
    }

    /**
     * application_date という名前でアクセスされたときの処理
     */
    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }

    // ==========================================
    // Blade側が直接参照しているプロパティ用のアクセサ
    // ==========================================

    /**
     * $application->new_date が呼ばれたときの処理
     */
    public function getNewDateAttribute()
    {
        if ($this->AttendanceRecord && $this->AttendanceRecord->date) {
            return Carbon::parse($this->AttendanceRecord->date);
        }

        return $this->clock_in_time ? Carbon::parse($this->clock_in_time) : null;
    }

    /**
     * $application->new_clock_in が呼ばれたときの処理
     */
    public function getNewClockInAttribute()
    {
        return $this->clock_in_time ? Carbon::parse($this->clock_in_time)->format('H:i') : '';
    }

    /**
     * $application->new_clock_out が呼ばれたときの処理
     */
    public function getNewClockOutAttribute()
    {
        return $this->clock_out_time ? Carbon::parse($this->clock_out_time)->format('H:i') : '';
    }

    /**
     * Blade側で proposalBreaks が使われた際、breaks(JSON) をループ可能なコレクションに変換して返す
     */
    public function getProposalBreaksAttribute()
    {
        if (empty($this->breaks)) {
            return collect();
        }

        $decoded = is_string($this->breaks)
            ? json_decode($this->breaks, true)
            : $this->breaks;

        if (! is_array($decoded)) {
            return collect();
        }

        return collect($decoded)->map(function ($item) {
            return (object) [
                'break_in' => $item['break_in_time'] ?? null,
                'break_out' => $item['break_out_time'] ?? null,
            ];
        });
    }
}
