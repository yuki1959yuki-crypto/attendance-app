<?php

namespace App\Actions\Fortify;

use App\Http\Requests\UserRegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        $request = new UserRegisterRequest;

        $validator = Validator::make($input, $request->rules(), $request->messages());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
