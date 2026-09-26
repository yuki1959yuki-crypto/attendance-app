<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * 管理者向けスタッフ（ユーザー）一覧画面の表示
     */
    public function index(): View
    {
        $users = User::all();

        return view('admin.staff-list', compact('users'));
    }
}
