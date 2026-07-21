<?php

use Illuminate\Console\Scheduling\Schedule;

test('the deployment health endpoint checks the database and writable storage', function () {
    $this->get('/up')->assertSuccessful();
});

test('the deployment health endpoint fails when persistent storage is unavailable', function () {
    config(['health.storage_path' => storage_path('missing-health-directory')]);

    $this->get('/up')->assertServerError();
});

test('production defaults use China time and expiring api tokens', function () {
    expect(config('app.timezone'))->toBe('Asia/Shanghai')
        ->and(config('sanctum.expiration'))->toBe(43200);
});

test('maintenance, pruning and backup tasks are registered with the scheduler', function () {
    $commands = collect(app(Schedule::class)->events())
        ->pluck('command')
        ->filter()
        ->join("\n");

    expect($commands)
        ->toContain('matters:close-expired')
        ->toContain('sanctum:prune-expired --hours=24')
        ->toContain('queue:prune-failed --hours=168')
        ->toContain('app:backup');
});
