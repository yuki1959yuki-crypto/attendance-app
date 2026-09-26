<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * 日次勤怠一覧（管理者トップ）の表示
     */
    public function index(Request $request): View
    {
        $dateInput = $request->input('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateInput);

        $users = User::all();

        $attendanceRecords = AttendanceRecord::whereDate('date', $date)
            ->with(['breakRecords', 'stampCorrectionRequests'])
            ->get();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        return view('admin.admin-attendance-list', compact(
            'users',
            'attendanceRecords',
            'date',
            'previousDay',
            'nextDay'
        ));
    }

    /**
     * 管理者の勤怠詳細画面の表示
     *
     * @param  int|string  $id
     */
    public function show($id): View
    {
        $attendanceRecord = AttendanceRecord::with('user')->findOrFail($id);
        $user = $attendanceRecord->user;

        return view('admin.admin-detail', compact('attendanceRecord', 'user'));
    }

    /**
     * 管理者の勤怠修正処理
     *
     * @param  int|string  $id
     */
    public function update(AttendanceRequest $request, $id): RedirectResponse
    {
        $attendance = AttendanceRecord::findOrFail($id);
        $date = $attendance->date;

        $clockInTime = $request->input('new_clock_in')
            ? $date.' '.$request->input('new_clock_in').':00'
            : null;

        $clockOutTime = $request->input('new_clock_out')
            ? $date.' '.$request->input('new_clock_out').':00'
            : null;

        $attendance->update([
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'comment' => $request->input('comment'),
        ]);

        return redirect()->route('admin.attendance.show', $id)
            ->with('success', '勤怠情報を更新しました。');
    }

    /**
     * スタッフ別月次勤怠一覧画面の表示
     *
     * @param  int|string  $id
     */
    public function monthly(Request $request, $id): View
    {
        $user = User::findOrFail($id);

        $dateInput = $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateInput);

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        $attendanceRecords = AttendanceRecord::where('user_id', $id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('breakRecords')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        $period = CarbonPeriod::create($startDate, $endDate);

        $formattedAttendanceRecords = collect($period)->map(function ($day) use ($attendanceRecords) {
            $dateStr = $day->format('Y-m-d');
            $record = $attendanceRecords->get($dateStr);

            return [
                'id' => $record ? $record->id : null,
                'date' => $day->format('Y-m-d'),
                'clock_in' => $record && $record->clock_in_time ? Carbon::parse($record->clock_in_time)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out_time ? Carbon::parse($record->clock_out_time)->format('H:i') : '',
                'total_break_time' => $record && isset($record->total_break_time) ? $record->total_break_time : null,
                'total_time' => $record && isset($record->total_time) ? $record->total_time : null,
            ];
        })->toArray();

        $previousMonth = $startDate->copy()->subMonth()->format('Y-m-d');
        $nextMonth = $startDate->copy()->addMonth()->format('Y-m-d');

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'formattedAttendanceRecords',
            'previousMonth',
            'nextMonth',
            'startDate',
            'endDate'
        ));
    }

    /**
     * 管理者の修正申請一覧画面の表示
     */
    public function correctionList(Request $request): View
    {
        $applications = StampCorrectionRequest::with(['user', 'attendanceRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 修正申請の承認処理（休憩の反映を含む）
     *
     * @param  int|string  $id
     */
    public function approve($id): RedirectResponse
    {
        $correctionRequest = StampCorrectionRequest::with(['attendanceRecord'])->findOrFail($id);

        if ($correctionRequest->status == 1) {
            return redirect('/admin/stamp_correction_request/list');
        }

        $attendanceRecord = $correctionRequest->attendanceRecord;
        if ($attendanceRecord) {
            $attendanceRecord->update([
                'clock_in_time' => $correctionRequest->clock_in_time ?? $attendanceRecord->clock_in_time,
                'clock_out_time' => $correctionRequest->clock_out_time ?? $attendanceRecord->clock_out_time,
                'memo' => $correctionRequest->comment ?? $attendanceRecord->memo,
            ]);

            $attendanceRecord->breakRecords()->delete();

            if ($correctionRequest->breaks) {
                $requestedBreaks = is_string($correctionRequest->breaks)
                    ? json_decode($correctionRequest->breaks, true)
                    : $correctionRequest->breaks;

                if (is_array($requestedBreaks)) {
                    foreach ($requestedBreaks as $break) {
                        $attendanceRecord->breakRecords()->create([
                            'break_in_time' => $break['break_in_time'] ?? null,
                            'break_out_time' => $break['break_out_time'] ?? null,
                        ]);
                    }
                }
            }
        }

        $correctionRequest->status = 1;
        $correctionRequest->save();

        return redirect('/admin/stamp_correction_request/list');
    }

    /**
     * 修正申請の詳細画面の表示
     *
     * @param  int|string  $id
     */
    public function approveIndex($id): View
    {
        $application = StampCorrectionRequest::with(['user', 'AttendanceRecord'])->findOrFail($id);
        $user = $application->user;

        $breaks = [];
        if (! empty($application->breaks)) {
            $decoded = is_string($application->breaks)
                ? json_decode($application->breaks, true)
                : $application->breaks;

            if (is_array($decoded)) {
                foreach ($decoded as $b) {
                    $breaks[] = [
                        'break_in' => isset($b['break_in_time']) ? Carbon::parse($b['break_in_time'])->format('H:i') : '',
                        'break_out' => isset($b['break_out_time']) ? Carbon::parse($b['break_out_time'])->format('H:i') : '',
                    ];
                }
            }
        }

        $data = [
            'id' => $application->id,
            'year' => Carbon::parse($application->attendanceRecord->date)->format('Y年'),
            'date' => Carbon::parse($application->attendanceRecord->date)->format('n月j日'),
            'clock_in' => $application->clock_in_time ? Carbon::parse($application->clock_in_time)->format('H:i') : '',
            'clock_out' => $application->clock_out_time ? Carbon::parse($application->clock_out_time)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => $application->comment,
            'application' => $application,
        ];

        return view('admin.admin-application-detail', compact('application', 'user', 'data'));
    }

    /**
     * スタッフの月次勤怠データをCSV出力する
     *
     * @return StreamedResponse
     */
    public function exportCsv(Request $request)
    {
        $userId = $request->input('user_id');
        $yearMonth = $request->input('year_month');

        $user = User::findOrFail($userId);

        $startDate = Carbon::parse($yearMonth.'-01')->startOfMonth();
        $endDate = Carbon::parse($yearMonth.'-01')->endOfMonth();

        $attendanceRecords = AttendanceRecord::where('user_id', $userId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with('breakRecords')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        $period = CarbonPeriod::create($startDate, $endDate);

        $fileName = 'attendance_'.$user->id.'_'.$yearMonth.'.csv';

        $callback = function () use ($period, $attendanceRecords) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['日付', '出勤', '退勤', '休憩時間', '合計勤務時間']);

            foreach ($period as $day) {
                $dateStr = $day->format('Y-m-d');
                $record = $attendanceRecords->get($dateStr);

                $clockIn = $record && $record->clock_in_time ? Carbon::parse($record->clock_in_time)->format('H:i') : '';
                $clockOut = $record && $record->clock_out_time ? Carbon::parse($record->clock_out_time)->format('H:i') : '';
                $breakTime = $record && isset($record->total_break_time) ? $record->total_break_time : '';
                $totalTime = $record && isset($record->total_time) ? $record->total_time : '';

                fputcsv($file, [
                    $day->format('Y/m/d'),
                    $clockIn,
                    $clockOut,
                    $breakTime,
                    $totalTime,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
