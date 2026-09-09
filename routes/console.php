<?php

use App\Models\Audit;
use Illuminate\Support\Facades\Schedule;

/**
 * Queue processing on shared hosting.
 *
 * There is no supervisor and no way to run a daemon here, so the queue
 * is drained once a minute by the scheduler instead. --stop-when-empty
 * matters: without it the worker would sit waiting for jobs and the
 * next minute's invocation would stack another one on top, until the
 * host kills them all.
 *
 * Set this up in the 20i control panel as a single cron entry, every
 * minute:
 *   /usr/php84/usr/bin/php /home/sites/41a/c/ce356e13a1/public_html/laravel12/artisan schedule:run
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=2')
    ->everyMinute()
    ->withoutOverlapping();

/**
 * An audit stuck in `running` means the worker died mid-job - the host
 * killed it, or PHP hit a limit. Without this it would sit there
 * forever telling the client their audit is still in progress, which is
 * worse than telling them it failed.
 */
Schedule::call(function () {
    Audit::withoutGlobalScopes()
        ->where('status', 'running')
        ->where('started_at', '<', now()->subMinutes(15))
        ->update([
            'status' => 'failed',
            'error' => 'The audit stopped unexpectedly and did not finish. Running it again is usually enough.',
            'finished_at' => now(),
        ]);
})->hourly();
