<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => 'nullable|date_format:H:i',
            'new_clock_out' => 'nullable|date_format:H:i',
            'comment' => 'required|string|max:255',
        ];
    }

    /**
     * エラーメッセージのカスタマイズ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required' => '備考を記入してください',
        ];
    }

    /**
     * 追加のバリデーションロジック（出退勤や休憩の前後関係チェック）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            if ($clockIn && $clockOut) {
                if ($clockIn >= $clockOut) {
                    $validator->errors()->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }
}
