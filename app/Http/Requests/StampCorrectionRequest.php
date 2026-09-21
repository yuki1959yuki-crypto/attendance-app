<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StampCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'comment' => ['required', 'string', 'max:255'],

            // ▼ 【追加】休憩時間のバリデーション
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * バリデーションエラーメッセージのカスタマイズ
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください。',
            'new_clock_in.date_format' => '出勤時間は「HH:mm」形式で入力してください。',
            'new_clock_out.required' => '退勤時間を入力してください。',
            'new_clock_out.date_format' => '退勤時間は「HH:mm」形式で入力してください。',
            'new_clock_out.after' => '出勤時間より前の時間は指定できません。',
            'comment.required' => '備考を記入してください。',
            'comment.max' => '備考は255文字以内で入力してください。',

            // ▼ 【追加】休憩エラーメッセージ
            'new_break_in.*.date_format' => '休憩開始時間は「HH:mm」形式で入力してください。',
            'new_break_out.*.date_format' => '休憩終了時間は「HH:mm」形式で入力してください。',
        ];
    }
}
