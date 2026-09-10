<?php

namespace App\Jobs;

use App\Models\CompetitorComparison;
use App\Services\DataForSeoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs one competitor comparison. Queued for the same reason every
 * other third-party lookup in this app is - a live HTTP call that a
 * client clicking a button should not sit and wait on.
 */
class RunCompetitorComparison implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public int $comparisonId)
    {
    }

    public function handle(DataForSeoService $dataForSeo): void
    {
        // withoutGlobalScopes for the same reason as every other job -
        // no authenticated user in a queue worker.
        $comparison = CompetitorComparison::withoutGlobalScopes()->with('site')->find($this->comparisonId);

        if (! $comparison || $comparison->status === 'completed') {
            return;
        }

        $comparison->update(['status' => 'running']);

        $result = $dataForSeo->compareDomains($comparison->site->url, $comparison->competitor_domain);

        $comparison->update([
            'status' => $result['error'] ? 'failed' : 'completed',
            'our_traffic' => $result['our_traffic'],
            'our_keywords' => $result['our_keywords'],
            'competitor_traffic' => $result['competitor_traffic'],
            'competitor_keywords' => $result['competitor_keywords'],
            'error' => $result['error'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Competitor comparison job failed', ['comparison_id' => $this->comparisonId, 'error' => $e->getMessage()]);

        CompetitorComparison::withoutGlobalScopes()->where('id', $this->comparisonId)->update([
            'status' => 'failed',
            'error' => 'The comparison did not complete. This is a fault in SynthSEO rather than the domains themselves.',
        ]);
    }
}
