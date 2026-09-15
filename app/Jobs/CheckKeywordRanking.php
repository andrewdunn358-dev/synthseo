<?php

namespace App\Jobs;

use App\Models\KeywordRanking;
use App\Models\TrackedKeyword;
use App\Services\DataForSeoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Checks one keyword's current rank and appends a new row to its
 * history - never overwrites the last check, since the whole point is
 * a trend built from many checks over time. Reschedules its own next
 * run (+7 days) regardless of success or failure, so one bad API call
 * doesn't silently stop a keyword from ever being checked again.
 */
class CheckKeywordRanking implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $trackedKeywordId)
    {
    }

    public function handle(DataForSeoService $dataForSeo): void
    {
        // withoutGlobalScopes for the same reason as every other job -
        // no authenticated user in a queue worker.
        $tracked = TrackedKeyword::withoutGlobalScopes()->with('site')->find($this->trackedKeywordId);

        if (! $tracked) {
            return;
        }

        $result = $dataForSeo->checkRanking($tracked->keyword, $tracked->site->url);

        KeywordRanking::create([
            'tracked_keyword_id' => $tracked->id,
            'rank' => $result['rank'],
            'error' => $result['error'],
            'checked_at' => now(),
        ]);

        $tracked->update(['next_check_at' => now()->addDays(7)]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Keyword ranking check failed', ['tracked_keyword_id' => $this->trackedKeywordId, 'error' => $e->getMessage()]);

        TrackedKeyword::withoutGlobalScopes()->where('id', $this->trackedKeywordId)
            ->update(['next_check_at' => now()->addDays(7)]);
    }
}
