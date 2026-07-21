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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
        $this->configureHealthChecks();
        $this->configureQueueLogging();
        $this->configureRateLimiting();
    }

    /** 非 AI 接口的基础防滥用限制。 */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('wechat-phone', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }

    /** 验活同时确认数据库可访问、持久化目录可写。 */
    protected function configureHealthChecks(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');

            $storagePath = config('health.storage_path');

            if (! is_string($storagePath) || ! is_dir($storagePath) || ! is_writable($storagePath)) {
                throw new \RuntimeException('持久化存储目录不可写');
            }
        });
    }

    /** 队列任务失败默认只进 failed_jobs 表，这里统一补一行日志。 */
    protected function configureQueueLogging(): void
    {
        Queue::failing(function (JobFailed $event): void {
            Log::error('队列任务失败', [
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
                'error' => $event->exception->getMessage(),
            ]);
        });
    }

    /**
     * `php artisan dev` 默认用 --host=localhost，macOS 上只监听 IPv6（::1），
     * 导致 127.0.0.1 打不开、小程序 wx.uploadFile（走 IPv4）失败——换成 0.0.0.0。
     */
    protected function configureDevCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        DevCommands::except('server');
        DevCommands::artisan('serve --host=0.0.0.0', 'api');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
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
