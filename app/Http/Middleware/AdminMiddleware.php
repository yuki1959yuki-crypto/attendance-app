<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || auth()->user()->admin_status != 1) {
            return redirect('/')->with('error', '管理者権限がありません。');
        }

        return $next($request);
    }
}
