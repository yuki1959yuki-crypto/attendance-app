<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ==========================================
// メール認証関連ルート
// ==========================================
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/attendance')->with('status', 'メール認証が完了しました！');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// 【一般ユーザー用ルート】（ログイン ＋ メール認証必須）
Route::middleware(['auth', 'verified'])->group(function () {
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

    Route::get('/stamp_correction_request/list', [App\Http\Controllers\Admin\AttendanceController::class, 'correctionList'])->name('correction.list');

    Route::get('/stamp_correction_request/approve/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'approveIndex'])->name('correction.approve.index');

    Route::post('/stamp_correction_request/approve/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'approve'])->name('correction.approve');

    Route::get('/attendance', [App\Http\Controllers\Admin\AttendanceController::class, 'index'])->name('index');

    Route::get('/attendance/list', [App\Http\Controllers\Admin\AttendanceController::class, 'index']);

    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    Route::get('/staff/list', [UserController::class, 'index']);

    Route::get('/staff/attendance/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'monthly']);

    Route::get('/staff/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'monthly']);

    Route::get('/attendance/staff/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'monthly'])->name('users.attendance');

    Route::get('/attendance/date/{date}', [App\Http\Controllers\Admin\AttendanceController::class, 'dateList'])->name('attendance.date');

    Route::get('/attendance/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'show'])->name('attendance.show');

    Route::post('/attendance/{id}', [App\Http\Controllers\Admin\AttendanceController::class, 'update'])->name('attendance.update');

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});

Route::middleware(['auth', 'admin'])->post('/export', [App\Http\Controllers\Admin\AttendanceController::class, 'exportCsv']);
