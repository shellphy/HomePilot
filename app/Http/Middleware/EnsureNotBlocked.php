<?php

namespace App\Http\Middleware;

use App\Models\Resident;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBlocked
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resident = $request->user('sanctum');

        abort_unless($resident instanceof Resident, 401);
        abort_if($resident->isBlocked(), 403, '你已被管理员限制参与社区互动');

        return $next($request);
    }
}
