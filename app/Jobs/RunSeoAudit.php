<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\PageSpeedService;
use App\Services\SeoAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs one audit and writes the result.
 *
 * Queued rather than run inline because a crawl involves three HTTP
 * requests to a third-party server, any of which can take the full
 * 20-second timeout. Doing that during a page load means a client
 * clicking "run audit" stares at a spinning browser for a minute and
 * then hits PHP's own execution limit.
 *
 * On shared hosting there is no supervisor, so the queue is driven by
 * cron (see routes/console.php). That means jobs run within a minute
 * rather than instantly - which is why the audit row is created in
 * `queued` state and the UI shows it, instead of the request pretending
 * nothing has happened yet.
 */
class RunSeoAudit implements ShouldQueue
{
    use Queueable;

    /** One retry. A site that is genuinely down will be down twice, and
     *  hammering a client's server to prove it is bad manners. */
    public int $tries = 2;

    /** Generous because PageSpeed alone can take 90 seconds on a slow
     *  site, on top of our own three requests. */
    public int $timeout = 240;

    public function __construct(public int $auditId)
    {
    }

    public function handle(SeoAuditService $engine, PageSpeedService $pageSpeed): void
    {
        // withoutGlobalScopes because a queued job has no authenticated
        // user, so the tenant scope would otherwise find nothing. The
        // audit already carries its own account_id - the tenant is
        // recorded on the row, not inferred from who is logged in.
        $audit = Audit::withoutGlobalScopes()->find($this->auditId);

        if (! $audit || $audit->status === 'completed') {
            return;
        }

        $audit->update(['status' => 'running', 'started_at' => now()]);

        $result = $engine->run($audit->url);

        // Lighthouse runs second and separately. Our own checks are the
        // ones that must always produce a result - PageSpeed is a
        // third-party call that fails routinely, and a rate limit at
        // Google's end must not cost the client their audit.
        $lighthouse = $pageSpeed->run($audit->url);

        $audit->findings()->delete();

        foreach ($result['findings'] as $finding) {
            $audit->findings()->create($finding + ['source' => 'synthseo']);
        }

        foreach ($lighthouse['findings'] as $finding) {
            $audit->findings()->create($finding);
        }

        $audit->update([
            'status' => $result['error'] && $result['findings'] === [] ? 'failed' : 'completed',
            'score' => $result['score'],
            'http_status' => $result['http_status'],
            'response_ms' => $result['response_ms'],
            'error' => $result['error'],
            'lh_performance' => $lighthouse['scores']['performance'] ?? null,
            'lh_seo' => $lighthouse['scores']['seo'] ?? null,
            'lh_accessibility' => $lighthouse['scores']['accessibility'] ?? null,
            'lh_best_practices' => $lighthouse['scores']['best-practices'] ?? null,
            'lh_lcp_ms' => $lighthouse['metrics']['lcp_ms'] ?? null,
            'lh_tbt_ms' => $lighthouse['metrics']['tbt_ms'] ?? null,
            'lh_cls' => $lighthouse['metrics']['cls'] ?? null,
            'lighthouse_strategy' => $lighthouse['strategy'],
            'lighthouse_final_url' => $lighthouse['final_url'],
            'lighthouse_error' => $lighthouse['error'],
            'finished_at' => now(),
        ]);

        // Any completed audit refreshes the schedule, whether it was
        // triggered by a click or by the scheduler itself - so turning
        // on weekly audits today starts a real 7-day clock from today,
        // not from whenever a previous run happened to be.
        //
        // The detected CMS is also written back here rather than kept
        // per-audit - a platform rarely changes, and the AI
        // recommendations prompt (see GenerateAuditRecommendations)
        // needs one current answer on the site, not a history of
        // guesses across old audits. Only overwritten when something
        // was actually detected this run - a run that found nothing
        // must not blank out a platform a previous run did detect.
        $site = $audit->site()->withoutGlobalScopes()->first();

        if ($site) {
            if ($site->audit_frequency !== 'off') {
                $site->rescheduleNextAudit();
            }

            if (! empty($result['cms']) && $result['cms'] !== $site->cms) {
                $site->update(['cms' => $result['cms']]);
            }
        }
    }

    /**
     * Called when the job throws rather than returning a result - a bug
     * in the engine, not a problem with the client's site. Recorded so
     * an audit never sits in `running` forever with no explanation.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SEO audit job failed', ['audit_id' => $this->auditId, 'error' => $e->getMessage()]);

        Audit::withoutGlobalScopes()->where('id', $this->auditId)->update([
            'status' => 'failed',
            'error' => 'The audit did not complete. This is a fault in SynthSEO rather than the site being audited.',
            'finished_at' => now(),
        ]);
    }
}
