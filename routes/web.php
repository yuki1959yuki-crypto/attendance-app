<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 【一般ユーザー用ルート】（ログイン必須）
Route::middleware(['auth'])->group(function () {
    // 打刻画面の表示
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');

    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');

    Route::get('/stamp_correction_request/list', [AttendanceController::class, 'correctionList'])->name('correction.list');

    Route::get('/application/{id}', [AttendanceController::class, 'showApplicationDetail']);

    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.show');

    Route::post('/attendance/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
});

// 【管理者用ルート】（ログイン ＋ 管理者権限必須）
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // ▼ 【追加】管理者の申請一覧（/admin/stamp_correction_request/list）
    Route::get('/stamp_correction_request/list', [App\Http\Controllers\Admin\AttendanceController::class, 'correctionList'])->name('correction.list');

    Route::get('/stamp_correction_request/approve/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'approveIndex'])->name('correction.approve.index');

    // ▼ 【追加】修正申請の承認処理（/admin/stamp_correction_request/approve/{id}）
    Route::post('/stamp_correction_request/approve/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'approve'])->name('correction.approve');

    // 日次勤怠一覧（/admin/attendance）
    Route::get('/attendance', [App\Http\Controllers\Admin\AttendanceController::class, 'index'])->name('index');

    Route::get('/attendance/list', [App\Http\Controllers\Admin\AttendanceController::class, 'index']);

    // ユーザー一覧（/admin/users）
    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    Route::get('/staff/list', [UserController::class, 'index']);

    // ▼ 【重要】 /staff/attendance/{id} は /staff/{id} よりも上に置く！
    Route::get('/staff/attendance/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'monthly']);

    Route::get('/staff/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'monthly']);

    // ▼ 【追加】スタッフ別月次勤怠一覧（/admin/attendance/staff/{id}） ※{id}より上に配置
    Route::get('/attendance/staff/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'monthly'])->name('users.attendance');

    // ★固定のパス（date/{date}）を {id} よりも上に配置する
    Route::get('/attendance/date/{date}', [App\Http\Controllers\Admin\AttendanceController::class, 'dateList'])->name('attendance.date');

    // 管理者の勤怠詳細表示（/admin/attendance/{id}）
    Route::get('/attendance/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'show'])->name('attendance.show');

    // 管理者の勤怠修正処理（POST /admin/attendance/{id}）
    Route::post('/attendance/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'update'])->name('attendance.update');

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});
