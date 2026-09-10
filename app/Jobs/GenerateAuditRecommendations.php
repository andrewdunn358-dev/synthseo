<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\ClaudeContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Turns one audit's findings into a plain-English recommendation.
 *
 * Queued for the same reason RunSeoAudit and GenerateContentPiece are -
 * a single third-party HTTP call that can take up to two minutes, and a
 * client clicking the button should not sit on a spinning page.
 */
class GenerateAuditRecommendations implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $auditId)
    {
    }

    public function handle(ClaudeContentService $claude): void
    {
        // withoutGlobalScopes for the same reason as RunSeoAudit - a
        // queued job has no authenticated user, so the tenant scope
        // would otherwise find nothing.
        $audit = Audit::withoutGlobalScopes()->with('site')->find($this->auditId);

        if (! $audit || $audit->recommendations_status === 'completed') {
            return;
        }

        $audit->update(['recommendations_status' => 'generating']);

        // Fail and pass severities only - a client asking "what should I
        // fix" does not need the passing checks read back to them.
        $findings = $audit->findings()
            ->whereIn('status', ['fail', 'warn'])
            ->get(['title', 'detail', 'status', 'severity', 'source'])
            ->map(fn ($f) => $f->toArray())
            ->all();

        // Most recent completed comparison only - a stale or failed
        // one would tell Claude nothing true. Silently absent (not an
        // error) when there is none yet, since a comparison is
        // optional and most audits will not have one.
        $comparison = $audit->site->competitorComparisons()
            ->where('status', 'completed')
            ->whereNotNull('our_traffic')
            ->whereNotNull('competitor_traffic')
            ->latest()
            ->first();

        $competitorContext = $comparison ? [
            'domain' => $comparison->competitor_domain,
            'ahead' => $comparison->leader() === 'us',
            'our_traffic' => $comparison->our_traffic,
            'competitor_traffic' => $comparison->competitor_traffic,
            'our_keywords' => $comparison->our_keywords,
            'competitor_keywords' => $comparison->competitor_keywords,
        ] : null;

        $result = $claude->generateRecommendations($findings, $audit->site->name, $audit->site->url, $competitorContext);

        $audit->update([
            'recommendations_status' => $result['error'] && ! $result['text'] ? 'failed' : 'completed',
            'recommendations' => $result['text'],
            'recommendations_error' => $result['error'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Audit recommendations job failed', ['audit_id' => $this->auditId, 'error' => $e->getMessage()]);

        Audit::withoutGlobalScopes()->where('id', $this->auditId)->update([
            'recommendations_status' => 'failed',
            'recommendations_error' => 'The recommendations did not generate. This is a fault in SynthSEO rather than the audit itself.',
        ]);
    }
}
