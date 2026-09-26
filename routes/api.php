<?php

use App\Http\Controllers\Api\V1\AttendanceApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 認証不要の読み取り系 API (v1)
Route::prefix('v1')->group(function () {
    Route::get('/attendance-records', [AttendanceApiController::class, 'index']);
    Route::get('/attendance-records/{id}', [AttendanceApiController::class, 'show']);
});

// Sanctum 認証が必要な書き込み系 API (v1)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/attendance-records', [AttendanceApiController::class, 'store']);
    Route::put('/attendance-records/{id}', [AttendanceApiController::class, 'update']);
    Route::delete('/attendance-records/{id}', [AttendanceApiController::class, 'destroy']);
});
