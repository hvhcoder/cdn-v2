<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn user bị ban không được truy cập hệ thống.
 */
class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isBanned()) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Tài khoản của bạn đã bị khóa. Lý do: '.($user->banned_reason ?? 'Vi phạm điều khoản sử dụng'),
            ]);
        }

        return $next($request);
    }
}
