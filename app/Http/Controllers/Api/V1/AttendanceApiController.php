<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttendanceRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRequest;
use App\Http\Resources\Api\V1\AttendanceResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    /**
     * 勤怠一覧の取得（認証不要、検索・絞り込み・ページネーション対応）
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRecord::with(['breakRecords', 'user']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        $attendances = $query->latest('date')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => AttendanceResource::collection($attendances)->response()->getData(true),
        ], 200);
    }

    /**
     * 勤怠詳細の取得（認証不要）
     */
    public function show($id): JsonResponse
    {
        $attendance = AttendanceRecord::with(['breakRecords', 'user', 'stampCorrectionRequests'])
            ->find($id);

        if (! $attendance) {
            return response()->json([
                'status' => 'error',
                'message' => '指定された勤怠データが見つかりません。',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new AttendanceResource($attendance),
        ], 200);
    }

    /**
     * 勤怠の新規登録（Sanctum認証必須）
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $attendance = AttendanceRecord::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => '勤怠データを登録しました。',
            'data' => new AttendanceResource($attendance),
        ], 201);
    }

    /**
     * 勤怠の更新（Sanctum認証必須 ＋ 認可）
     */
    public function update(UpdateAttendanceRequest $request, $id): JsonResponse
    {
        $attendance = AttendanceRecord::find($id);

        if (! $attendance) {
            return response()->json([
                'status' => 'error',
                'message' => '指定された勤怠データが見つかりません。',
            ], 404);
        }

        $this->authorize('update', $attendance);

        $attendance->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => '勤怠データを更新しました。',
            'data' => new AttendanceResource($attendance),
        ], 200);
    }

    /**
     * 勤怠の削除（Sanctum認証必須 ＋ 認可）
     */
    public function destroy($id): JsonResponse
    {
        $attendance = AttendanceRecord::find($id);

        if (! $attendance) {
            return response()->json([
                'status' => 'error',
                'message' => '指定された勤怠データが見つかりません。',
            ], 404);
        }

        $this->authorize('delete', $attendance);

        $attendance->delete();

        return response()->json([
            'status' => 'success',
            'message' => '勤怠データを削除しました。',
        ], 200);
    }
}
