<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API 的未登录请求直接 401 JSON（默认会试图重定向到不存在的 login 路由）
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // HttpException 在框架的 internalDontReport 里，report 回调不会触发，
        // 得先 stopIgnoring 捞回来自己分级记，再返回 false 挡住默认日志栈。
        $exceptions->stopIgnoring(HttpException::class);

        $exceptions->report(function (HttpException $e): bool {
            $status = $e->getStatusCode();
            $request = request();

            $context = [
                'status' => $status,
                'method' => $request->method(),
                'path' => $request->path(),
                'resident_id' => $request->user()?->id,
                'reason' => $e->getMessage(),
            ];

            match (true) {
                $status >= 500 => Log::error('请求异常终止', $context),
                in_array($status, [401, 403], true) => Log::warning('请求被拒绝', $context),
                default => null,
            };

            return false;
        });
    })->create();
