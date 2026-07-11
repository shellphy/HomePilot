<?php

namespace App\Http\Middleware;

use App\Models\Resident;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理端接口守卫：管理员就是被授权的普通成员（is_admin），
 * 授权用 php artisan admin:grant，管理操作全部在小程序内完成。
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $resident = $request->user('sanctum');

        abort_unless($resident instanceof Resident && $resident->is_admin, 403, '需要管理员权限');

        return $next($request);
    }
}
