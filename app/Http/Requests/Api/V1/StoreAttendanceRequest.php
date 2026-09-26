<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'clock_in_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'clock_out_time' => ['nullable', 'date_format:Y-m-d H:i:s', 'after:clock_in_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'ユーザーIDは必須です。',
            'user_id.exists' => '指定されたユーザーが存在しません。',
            'date.required' => '日付は必須です。',
            'date.date' => '有効な日付形式で入力してください。',
            'clock_in_time.required' => '出勤時間は必須です。',
            'clock_in_time.date_format' => '出勤時間は YYYY-MM-DD HH:MM:SS 形式で入力してください。',
            'clock_out_time.date_format' => '退勤時間は YYYY-MM-DD HH:MM:SS 形式で入力してください。',
            'clock_out_time.after' => '退勤時間は出勤時間より後の時間を指定してください。',
        ];
    }

    /**
     * API向けにバリデーション失敗時のレスポンスをJSON化する
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'バリデーションエラーが発生しました。',
            'errors' => $validator->errors(),
        ], 422));
    }
}
