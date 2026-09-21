<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = User::where('email', $this->email)->first();

            // ユーザーが存在し、かつ admin_status が 1（管理者）でない場合
            if ($user && $user->admin_status != 1) {
                $validator->errors()->add('email', '管理者権限を持つアカウントではありません。');
            }
        });
    }
}
