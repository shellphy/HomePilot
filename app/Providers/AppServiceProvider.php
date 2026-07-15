<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
        $this->configureQueueLogging();
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
