<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // 1. 打刻画面の表示
    public function index()
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

    // 2. 打刻アクションの処理（出勤・退勤・休憩などの受付）
    public function store(Request $request)
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
                    // ▼ 【追加】すでに未終了の休憩レコードがないかチェックする
                    $hasActiveBreak = BreakRecord::where('attendance_record_id', $attendance->id)
                        ->whereNull('break_out_time')
                        ->exists();

                    // 休憩中でなければ、新しい休憩を作成する
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

    public function list(Request $request)
    {
        $user = Auth::user();

        // クエリパラメータから日付を取得（デフォルトは今日）
        $dateInput = $request->input('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateInput);

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // 該当月の自分の勤怠データを取得
        $attendances = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('breakRecords')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // 1日〜月末までの全日付分の配列を生成（データがない日は空欄にするため）
        $formattedAttendanceRecords = [];
        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);

        foreach ($period as $day) {
            $dateStr = $day->format('Y-m-d');
            $record = $attendances->get($dateStr);

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null,
                'date' => $day->format('m/d'), // または表示形式に合わせて調整
                'clock_in' => $record && $record->clock_in_time ? Carbon::parse($record->clock_in_time)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out_time ? Carbon::parse($record->clock_out_time)->format('H:i') : '',
                'total_break_time' => $record ? $record->total_break_time : '',
                'total_time' => $record ? $record->total_working_time : '',
            ];
        }

        // 前月・翌月の文字列生成
        $previousMonth = $date->copy()->subMonth()->format('Y-m-d');
        $nextMonth = $date->copy()->addMonth()->format('Y-m-d');

        return view('user.user-attendance-list', compact(
            'formattedAttendanceRecords',
            'date',
            'previousMonth',
            'nextMonth'
        ));
    }

    // ==========================================
    // 4. 勤怠詳細画面の表示 (US008)
    // ==========================================
    public function show($id)
    {
        $user = Auth::user();

        // ▼ 【修正】もし管理者の場合は user_id の縛りをなくして任意の勤怠を取得できるようにする
        $query = AttendanceRecord::with(['breakRecords', 'stampCorrectionRequests']);

        if ($user->admin_status) { // ※お使いの管理者判定カラム（例: admin_status, role 等）に合わせてください
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
            // ▼ 見本の配置に合わせて「年」と・「月日」に分割して渡す
            $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),      // 例: 2026年
            'date' => Carbon::parse($attendance->date)->format('n月j日'),   // 例: 9月16日
            'clock_in' => $attendance->clock_in_time ? Carbon::parse($attendance->clock_in_time)->format('H:i') : '',
            'clock_out' => $attendance->clock_out_time ? Carbon::parse($attendance->clock_out_time)->format('H:i') : '',
            'application' => $pendingApplication,
            'breaks' => $breaks,
            'comment' => $pendingApplication ? $pendingApplication->comment : '', // 申請中なら理由などを表示
        ];

        return view('user.user-detail', compact('data', 'user'));
    }

    // ==========================================
    // 5. 勤怠修正申請の保存処理 (US008)
    // ==========================================
    public function update(\App\Http\Requests\StampCorrectionRequest $request, $id)
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

        // ▼ 送信された複数の休憩データを配列にまとめる
        $newBreakIns = $request->input('new_break_in', []);
        $newBreakOuts = $request->input('new_break_out', []);
        $breaksData = [];

        foreach ($newBreakIns as $index => $breakIn) {
            $breakOut = $newBreakOuts[$index] ?? null;
            // 開始または終了のどちらかに入力がある場合のみ有効な休憩として保持
            if (! empty($breakIn) || ! empty($breakOut)) {
                $breaksData[] = [
                    'break_in_time' => $breakIn ? $date.' '.$breakIn.':00' : null,
                    'break_out_time' => $breakOut ? $date.' '.$breakOut.':00' : null,
                ];
            }
        }

        // 修正申請データ保存（breaksカラムにJSONとして格納）
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

    // ==========================================
    // 6. 申請一覧画面の表示（承認待ち・承認済み）
    // ==========================================
    public function correctionList(Request $request)
    {
        $user = Auth::user();

        // ログインユーザーの修正申請データをすべて取得（リレーションの勤怠情報も含む）
        $requests = StampCorrectionRequest::where('user_id', $user->id)
            ->with(['attendanceRecord'])
            ->latest()
            ->get();

        // Blade側（$formattedApplications）の形式にデータを整形
        $formattedApplications = $requests->map(function ($item) {
            // ステータスの数値（0: 承認待ち, 1: 承認済み など）を文字列に変換
            // ※必要に応じてステータスの数値ルールに合わせて調整してください
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

    // ==========================================
    // 7. 申請詳細（/application/{id}）からの遷移処理
    // ==========================================
    public function showApplicationDetail($id)
    {
        $user = Auth::user();

        // 該当する修正申請データを取得
        $correctionRequest = StampCorrectionRequest::where('user_id', $user->id)
            ->findOrFail($id);

        // 紐づいている勤怠詳細画面（/attendance/{id}）へリダイレクトする
        return redirect()->route('attendance.show', $correctionRequest->attendance_record_id);
    }
}
