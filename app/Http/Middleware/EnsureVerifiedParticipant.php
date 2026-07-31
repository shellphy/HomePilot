<?php

namespace App\Http\Middleware;

use App\Models\Resident;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedParticipant
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

        if (! $resident->isVerifiedParticipant()) {
            Log::warning('未认证用户尝试受限操作', [
                'resident_id' => $resident->id,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => '请先完成身份认证',
                'code' => 'verification_required',
            ], 403);
        }

        return $next($request);
    }
}
