<?php

namespace App\Http\Middleware;

use App\Models\Resident;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        /** @var Resident $resident */
        $resident = $request->user('sanctum');

        if ($resident->isBlocked()) {
            Log::warning('封禁用户尝试社区互动', [
                'resident_id' => $resident->id,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);

            abort(403, '你已被管理员限制参与社区互动');
        }

        return $next($request);
    }
}
