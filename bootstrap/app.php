<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API 未登录时返回 JSON 401，不执行网页登录跳转。
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'feature' => EnsureFeatureEnabled::class,
            'not_blocked' => EnsureNotBlocked::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
