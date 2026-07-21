<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
        $this->configureHealthChecks();
        $this->configureQueueLogging();
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('wechat-phone', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->getAuthIdentifier()));
    }

    protected function configureHealthChecks(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');

            $storagePath = config('health.storage_path');

            if (! is_writable($storagePath)) {
                throw new \RuntimeException('持久化存储目录不可写');
            }
        });
    }

    protected function configureQueueLogging(): void
    {
        Queue::failing(function (JobFailed $event): void {
            Log::error('队列任务失败', [
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
                'exception' => $event->exception,
            ]);
        });
    }

    protected function configureDevCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // 小程序通过 IPv4 访问本地开发服务。
        DevCommands::except('server');
        DevCommands::artisan('serve --host=0.0.0.0', 'api');
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
