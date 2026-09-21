<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    // ==========================================
    // 1. 日次勤怠一覧（管理者トップ）の表示
    // ==========================================
    public function index(Request $request)
    {
        // クエリパラメータから日付を取得（デフォルトは今日）
        $dateInput = $request->input('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateInput);

        // ▼ 全ユーザーを取得
        $users = User::all();

        // ▼ 指定された日付の全勤怠データを取得（変数名を $attendanceRecords に合わせる）
        $attendanceRecords = AttendanceRecord::whereDate('date', $date)
            ->with(['breakRecords', 'stampCorrectionRequests'])
            ->get();

        // 前日・翌日の日付文字列
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

    // ==========================================
    // 2. 管理者の勤怠詳細画面の表示
    // ==========================================
    public function show($id)
    {
        // 1. 勤怠レコードを関連するユーザー情報と一緒に取得
        $attendanceRecord = AttendanceRecord::with('user')->findOrFail($id);

        // 2. ユーザー情報を取得
        $user = $attendanceRecord->user;

        // 3. ビューに変数を渡して表示
        return view('admin.admin-detail', compact('attendanceRecord', 'user'));
    }

    // ==========================================
    // 3. 管理者の勤怠修正処理
    // ==========================================
    // 忘れずにファイルの上部で AttendanceRequest をインポートしておきます
    public function update(AttendanceRequest $request, $id)
    {
        // 1. バリデーションは AttendanceRequest で自動実行されます

        // 2. 該当の勤怠レコードを取得
        $attendance = AttendanceRecord::findOrFail($id);

        // 3. 画面から送られた時間に、この勤怠の日付を結合する
        $date = $attendance->date;

        $clockInTime = $request->input('new_clock_in')
            ? $date.' '.$request->input('new_clock_in').':00'
            : null;

        $clockOutTime = $request->input('new_clock_out')
            ? $date.' '.$request->input('new_clock_out').':00'
            : null;

        // 4. 組み立てた日時データと備考を保存
        $attendance->update([
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'comment' => $request->input('comment'),
        ]);

        // 5. 更新後、詳細画面へリダイレクト
        return redirect()->route('admin.attendance.show', $id)
            ->with('success', '勤怠情報を更新しました。');
    }

    /**
     * スタッフ別月次勤怠一覧画面
     */
    public function monthly(Request $request, $id)
    {
        // 1. 対象のユーザー（スタッフ）を取得
        $user = User::findOrFail($id);

        // 2. クエリパラメータから日付を取得（なければ今月）
        $dateInput = $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateInput);

        // 3. 該当月の開始日と終了日を算出
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        // 4. 該当ユーザーの当月の勤怠データを取得して日付でキーイング
        $attendanceRecords = AttendanceRecord::where('user_id', $id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('breakRecords')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // 5. ブレード側が求めている $formattedAttendanceRecords を1ヶ月分の日付ごとに生成
        $formattedAttendanceRecords = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $day) {
            $dateStr = $day->format('Y-m-d');
            $record = $attendanceRecords->get($dateStr);

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null, // 詳細リンク用のID
                'date' => $day->format('Y-m-d'),
                'clock_in' => $record && $record->clock_in_time ? Carbon::parse($record->clock_in_time)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out_time ? Carbon::parse($record->clock_out_time)->format('H:i') : '',
                'total_break_time' => $record && isset($record->total_break_time) ? $record->total_break_time : null,
                'total_time' => $record && isset($record->total_time) ? $record->total_time : null, // 【追加】合計勤務時間
            ];
        }

        // 6. 前月・翌月のリンク用日付
        $previousMonth = $startDate->copy()->subMonth()->format('Y-m-d');
        $nextMonth = $startDate->copy()->addMonth()->format('Y-m-d');

        // 7. ビューへデータを渡して表示
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
     * 4. 管理者の修正申請一覧画面
     */
    public function correctionList(Request $request)
    {

        // ビューが求めている変数名 $applications に合わせる
        $applications = StampCorrectionRequest::with(['user', 'attendanceRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 5. 修正申請の承認処理（休憩の反映を含む）
     */
    public function approve($id)
    {
        $correctionRequest = StampCorrectionRequest::with(['attendanceRecord'])->findOrFail($id);

        if ($correctionRequest->status == 1) {
            return redirect('/admin/stamp_correction_request/list');
        }

        $attendanceRecord = $correctionRequest->attendanceRecord;
        if ($attendanceRecord) {
            // 1. 出勤・退勤・メモの更新
            $attendanceRecord->update([
                'clock_in_time' => $correctionRequest->clock_in_time ?? $attendanceRecord->clock_in_time,
                'clock_out_time' => $correctionRequest->clock_out_time ?? $attendanceRecord->clock_out_time,
                'memo' => $correctionRequest->comment ?? $attendanceRecord->memo,
            ]);

            // 2. 既存の break_records を一度すべて削除
            $attendanceRecord->breakRecords()->delete();

            // 3. 申請に含まれていた複数の休憩データを break_records に再登録
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

        // 4. ステータスを承認済み（1）に変更
        $correctionRequest->status = 1;
        $correctionRequest->save();

        return redirect('/admin/stamp_correction_request/list');
    }

    /**
     * 5. 修正申請の詳細画面表示
     */
    public function approveIndex($id)
    {
        // 修正申請データを取得（userやAttendanceRecordも一緒にロード）
        $application = StampCorrectionRequest::with(['user', 'AttendanceRecord'])->findOrFail($id);

        $user = $application->user;

        // ▼ 【追加】申請データの breaks (JSON) を配列にデコードしてビューに合わせる加工をする
        // もし既存のBladeが $data という配列や特定の形式を求めている場合はここに合わせます
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

        // ビュー側で使いやすいように $data 配列を組み立てて渡す（または既存の compact に追加）
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
}
