<?php

use App\Jobs\CheckKeywordRanking;
use App\Jobs\RunSeoAudit;
use App\Models\Audit;
use App\Models\Site;
use App\Models\TrackedKeyword;
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

/**
 * Recurring audits. Runs every 15 minutes rather than daily - the
 * queue itself only checks once a minute, so a site due at 09:03 that
 * only got noticed at midnight would look neglected even though
 * nothing is actually wrong. This just finds sites whose next_audit_at
 * has arrived and queues them exactly like a manual click would.
 *
 * withoutGlobalScopes for the same reason as everywhere else in
 * console.php - a scheduled command has no logged-in user.
 */
Schedule::call(function () {
    Site::withoutGlobalScopes()
        ->where('audit_frequency', '!=', 'off')
        ->whereNotNull('next_audit_at')
        ->where('next_audit_at', '<=', now())
        ->each(function (Site $site) {
            $audit = $site->audits()->create([
                'account_id' => $site->account_id,
                'url' => $site->url,
                'status' => 'queued',
            ]);

            RunSeoAudit::dispatch($audit->id);
        });
})->everyFifteenMinutes();

/**
 * Recurring keyword rank checks - same reasoning as the audit
 * scheduler above, just for tracked_keywords.next_check_at instead of
 * sites.next_audit_at. CheckKeywordRanking itself sets the next
 * check date +7 days each time it runs.
 */
Schedule::call(function () {
    TrackedKeyword::withoutGlobalScopes()
        ->where(function ($query) {
            $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
        })
        ->each(fn (TrackedKeyword $tracked) => CheckKeywordRanking::dispatch($tracked->id));
})->everyFifteenMinutes();
