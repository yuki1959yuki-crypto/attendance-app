<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * 打刻画面の表示
     */
    public function index(): View
    {
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');

        $formattedDate = Carbon::now()->format('Y年m月d日');
        $formattedTime = Carbon::now()->format('H:i');

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return view('user.attendance-register', compact('user', 'attendance', 'formattedDate', 'formattedTime'));
    }

    /**
     * 打刻アクションの処理（出勤・退勤・休憩などの受付）
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();
        $action = $request->input('action');

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        switch ($action) {
            case 'clock_in':
                if (! $attendance) {
                    AttendanceRecord::create([
                        'user_id' => $user->id,
                        'date' => $today,
                        'clock_in_time' => $now,
                    ]);
                }
                break;

            case 'clock_out':
                if ($attendance && $attendance->clock_in_time && ! $attendance->clock_out_time) {
                    $attendance->update([
                        'clock_out_time' => $now,
                    ]);
                }
                break;

            case 'break_in':
                if ($attendance && $attendance->clock_in_time && ! $attendance->clock_out_time) {
                    $hasActiveBreak = BreakRecord::where('attendance_record_id', $attendance->id)
                        ->whereNull('break_out_time')
                        ->exists();

                    if (! $hasActiveBreak) {
                        BreakRecord::create([
                            'attendance_record_id' => $attendance->id,
                            'break_in_time' => $now,
                        ]);
                    }
                }
                break;

            case 'break_out':
                if ($attendance) {
                    $breakRecord = BreakRecord::where('attendance_record_id', $attendance->id)
                        ->whereNull('break_out_time')
                        ->latest()
                        ->first();

                    if ($breakRecord) {
                        $breakRecord->update([
                            'break_out_time' => $now,
                        ]);
                    }
                }
                break;
        }

        return redirect()->route('attendance.index');
    }

    /**
     * ユーザーの勤怠一覧画面の表示
     */
    public function list(Request $request): View
    {
        $user = Auth::user();

        $dateInput = $request->input('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateInput);

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendances = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('breakRecords')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);

        $formattedAttendanceRecords = collect($period)->map(function ($day) use ($attendances) {
            $dateStr = $day->format('Y-m-d');
            $record = $attendances->get($dateStr);

            return [
                'id' => $record ? $record->id : null,
                'date' => $day->format('m/d'),
                'clock_in' => $record && $record->clock_in_time ? Carbon::parse($record->clock_in_time)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out_time ? Carbon::parse($record->clock_out_time)->format('H:i') : '',
                'total_break_time' => $record ? $record->total_break_time : '',
                'total_time' => $record ? $record->total_working_time : '',
            ];
        })->toArray();

        $previousMonth = $date->copy()->subMonth()->format('Y-m-d');
        $nextMonth = $date->copy()->addMonth()->format('Y-m-d');

        return view('user.user-attendance-list', compact(
            'formattedAttendanceRecords',
            'date',
            'previousMonth',
            'nextMonth'
        ));
    }

    /**
     * ログインユーザーの直近6ヶ月の勤怠レポート画面の表示
     */
    public function report(): View
    {
        $user = Auth::user();

        $endMonth = Carbon::today()->startOfMonth();
        $startMonth = $endMonth->copy()->subMonths(5)->startOfMonth();

        $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startMonth->format('Y-m-d'), $endMonth->copy()->endOfMonth()->format('Y-m-d')])
            ->with('breakRecords')
            ->get();

        $monthlyTrend = collect(CarbonPeriod::create($startMonth, '1 month', $endMonth))
            ->map(function ($month) use ($attendanceRecords) {
                $yearMonthStr = $month->format('Y-m');

                $recordsInMonth = $attendanceRecords->filter(function ($record) use ($yearMonthStr) {
                    return Carbon::parse($record->date)->format('Y-m') === $yearMonthStr;
                });

                $workMinutes = 0;
                $overtimeMinutes = 0;

                foreach ($recordsInMonth as $record) {
                    if ($record->clock_in_time && $record->clock_out_time) {
                        $in = Carbon::parse($record->clock_in_time);
                        $out = Carbon::parse($record->clock_out_time);
                        $totalMinutes = $in->diffInMinutes($out);

                        $breakMinutes = 0;
                        foreach ($record->breakRecords as $break) {
                            if ($break->break_in_time && $break->break_out_time) {
                                $breakMinutes += Carbon::parse($break->break_in_time)->diffInMinutes(Carbon::parse($break->break_out_time));
                            }
                        }

                        $netMinutes = max(0, $totalMinutes - $breakMinutes);
                        $workMinutes += $netMinutes;

                        if ($netMinutes > 480) {
                            $overtimeMinutes += ($netMinutes - 480);
                        }
                    }
                }

                return [
                    'month' => $month->format('Y年n月'),
                    'work_minutes' => $workMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                ];
            })->values();

        $totalWorkMinutes = $monthlyTrend->sum('work_minutes');
        $totalOvertimeMinutes = $monthlyTrend->sum('overtime_minutes');

        $totalDays = $attendanceRecords->filter(function ($record) {
            return ! empty($record->clock_in_time) && ! empty($record->clock_out_time);
        })->count();

        $avgWorkMinutes = $totalDays > 0 ? round($totalWorkMinutes / $totalDays) : 0;

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $avgWorkMinutes,
        ];

        $currentMonthStr = Carbon::today()->format('Y-m');
        $currentMonthRecords = $attendanceRecords->filter(function ($record) use ($currentMonthStr) {
            return Carbon::parse($record->date)->format('Y-m') === $currentMonthStr;
        });

        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        foreach ($currentMonthRecords as $record) {
            if ($record->clock_in_time) {
                $clockIn = Carbon::parse($record->clock_in_time);
                if ($clockIn->format('H:i') > '09:00') {
                    $lateCount++;
                }
            }

            if ($record->clock_out_time) {
                $clockOut = Carbon::parse($record->clock_out_time);
                if ($clockOut->format('H:i') < '18:00') {
                    $earlyLeaveCount++;
                }
            }

            if ($record->clock_in_time && $record->clock_out_time) {
                $in = Carbon::parse($record->clock_in_time);
                $out = Carbon::parse($record->clock_out_time);
                $totalMinutes = $in->diffInMinutes($out);

                $breakMinutes = 0;
                foreach ($record->breakRecords as $break) {
                    if ($break->break_in_time && $break->break_out_time) {
                        $breakMinutes += Carbon::parse($break->break_in_time)->diffInMinutes(Carbon::parse($break->break_out_time));
                    }
                }

                $netWorkingMinutes = max(0, $totalMinutes - $breakMinutes);

                if ($netWorkingMinutes > 600) {
                    $longWorkCount++;
                }
            }
        }

        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        return view('reports.index', compact('user', 'summary', 'monthlyTrend', 'anomalies'));
    }

    /**
     * 勤怠詳細画面の表示
     *
     * @param  int|string  $id
     */
    public function show($id): View
    {
        $user = Auth::user();

        $query = AttendanceRecord::with(['breakRecords', 'stampCorrectionRequests']);

        if ($user->admin_status) {
            $attendance = $query->findOrFail($id);
        } else {
            $attendance = $query->where('user_id', $user->id)->findOrFail($id);
        }

        $pendingApplication = $attendance->stampCorrectionRequests()
            ->where('status', 'pending')
            ->first();

        $breaks = $attendance->breakRecords->map(function ($break) {
            return [
                'id' => $break->id,
                'break_in' => $break->break_in_time ? Carbon::parse($break->break_in_time)->format('H:i') : '',
                'break_out' => $break->break_out_time ? Carbon::parse($break->break_out_time)->format('H:i') : '',
            ];
        })->toArray();

        $data = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('n月j日'),
            'clock_in' => $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time)->format('H:i') : '',
            'clock_out' => $attendance->clock_out_time ? Carbon::parse($attendance->clock_out_time)->format('H:i') : '',
            'application' => $pendingApplication,
            'breaks' => $breaks,
            'comment' => $pendingApplication ? $pendingApplication->comment : '',
        ];

        return view('user.user-detail', compact('data', 'user'));
    }

    /**
     * 勤怠修正申請の保存処理
     *
     * @param  int|string  $id
     */
    public function update(\App\Http\Requests\StampCorrectionRequest $request, $id): RedirectResponse
    {
        $user = Auth::user();

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->findOrFail($id);

        $existingPending = $attendance->stampCorrectionRequests()
            ->where('status', 0)
            ->exists();

        if ($existingPending) {
            return redirect()->back()->with('error', '既に承認待ちの申請が存在するため修正できません。');
        }

        $date = $attendance->date;

        $clockInDateTime = $request->input('new_clock_in')
            ? $date.' '.$request->input('new_clock_in').':00'
            : null;

        $clockOutDateTime = $request->input('new_clock_out')
            ? $date.' '.$request->input('new_clock_out').':00'
            : null;

        $newBreakIns = $request->input('new_break_in', []);
        $newBreakOuts = $request->input('new_break_out', []);
        $breaksData = [];

        foreach ($newBreakIns as $index => $breakIn) {
            $breakOut = $newBreakOuts[$index] ?? null;
            if (! empty($breakIn) || ! empty($breakOut)) {
                $breaksData[] = [
                    'break_in_time' => $breakIn ? $date.' '.$breakIn.':00' : null,
                    'break_out_time' => $breakOut ? $date.' '.$breakOut.':00' : null,
                ];
            }
        }

        StampCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $attendance->id,
            'clock_in_time' => $clockInDateTime,
            'clock_out_time' => $clockOutDateTime,
            'comment' => $request->input('comment'),
            'breaks' => ! empty($breaksData) ? json_encode($breaksData) : null,
            'status' => 0,
        ]);

        return redirect()->route('attendance.show', $id)->with('success', '修正申請を送信しました。');
    }

    /**
     * 申請一覧画面の表示（承認待ち・承認済み）
     */
    public function correctionList(Request $request): View
    {
        $user = Auth::user();

        $requests = StampCorrectionRequest::where('user_id', $user->id)
            ->with(['attendanceRecord'])
            ->latest()
            ->get();

        $formattedApplications = $requests->map(function ($item) {
            $statusText = match ((int) $item->status) {
                0 => '承認待ち',
                1 => '承認済み',
                default => 'その他',
            };

            return [
                'id' => $item->id,
                'approval_status' => $statusText,
                'date' => $item->attendanceRecord ? Carbon::parse($item->attendanceRecord->date)->format('Y年n月j日') : '',
                'comment' => $item->comment,
                'application_date' => Carbon::parse($item->created_at)->format('Y年n月j日'),
            ];
        });

        return view('user.user-application-list', compact('formattedApplications', 'user'));
    }

    /**
     * 申請詳細からの遷移処理
     *
     * @param  int|string  $id
     */
    public function showApplicationDetail($id): RedirectResponse
    {
        $user = Auth::user();

        $correctionRequest = StampCorrectionRequest::where('user_id', $user->id)
            ->findOrFail($id);

        return redirect()->route('attendance.show', $correctionRequest->attendance_record_id);
    }
}
