<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttendanceRecord $attendanceRecord): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
    }

    /**
     * 勤怠データの更新権限があるか
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 勤怠データの削除権限があるか
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AttendanceRecord $attendanceRecord): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        //
    }
}
