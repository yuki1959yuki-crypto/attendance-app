<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakRecord extends Model
{
    use HasFactory;

    protected $table = 'breaks';

    protected $fillable = [
        'attendance_record_id',
        'break_in_time',
        'break_out_time',
    ];

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
