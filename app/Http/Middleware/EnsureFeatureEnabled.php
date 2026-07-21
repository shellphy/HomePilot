<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! config("features.{$feature}")) {
            Log::info('已关闭的功能被调用', [
                'feature' => $feature,
                'resident_id' => $request->user()?->getAuthIdentifier(),
                'path' => $request->path(),
            ]);

            abort(404);
        }

        return $next($request);
    }
}
