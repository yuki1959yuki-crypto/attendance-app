<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User; // Userモデルをインポート

class UserController extends Controller
{
    public function index()
    {
        // 登録されているユーザー一覧を取得（必要に応じて一般ユーザーのみに絞り込み）
        $users = User::all();

        // adminフォルダ内の staff-list.blade.php を呼び出し、データを渡す
        return view('admin.staff-list', compact('users'));
    }
}
