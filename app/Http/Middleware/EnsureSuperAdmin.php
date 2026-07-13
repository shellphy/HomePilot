<?php

namespace App\Http\Middleware;

use App\Models\Resident;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 超级管理员接口守卫：只有超级管理员能增减管理员（创始人由 php artisan admin:grant --super 种下）。
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $resident = $request->user('sanctum');

        abort_unless($resident instanceof Resident && $resident->is_super_admin, 403, '需要超级管理员权限');

        return $next($request);
    }
}
