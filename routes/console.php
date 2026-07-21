<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('matters:close-expired')->everyMinute()->withoutOverlapping(10);
Schedule::command('sanctum:prune-expired --hours=24')->daily()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily()->withoutOverlapping();
Schedule::command('app:backup')->dailyAt((string) config('backup.time'))->withoutOverlapping(60);
