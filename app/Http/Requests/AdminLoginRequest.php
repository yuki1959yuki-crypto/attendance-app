<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminLoginRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを行う権限を持っているか判定する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストに適用されるバリデーションルールを取得する
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * バリデーターインスタンスの設定
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = User::where('email', $this->email)->first();

            if ($user && $user->admin_status != 1) {
                $validator->errors()->add('email', '管理者権限を持つアカウントではありません。');
            }
        });
    }
}
