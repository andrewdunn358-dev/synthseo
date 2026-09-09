<?php

namespace App\Jobs;

use App\Models\ContentPiece;
use App\Services\ClaudeContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates one content piece and writes the result.
 *
 * Queued for the same reason RunSeoAudit is: this is a single HTTP call
 * to a third party that can legitimately take up to two minutes, and a
 * client clicking "generate" should not sit on a spinning page waiting
 * for it. Driven by the same cron-triggered scheduler - see
 * routes/console.php.
 */
class GenerateContentPiece implements ShouldQueue
{
    use Queueable;

    /** One retry, same reasoning as RunSeoAudit - a key that is wrong
     *  or a prompt that is rejected will be wrong twice. */
    public int $tries = 2;

    /** Above the service's own 120s timeout so the job itself is never
     *  the thing that cuts a slow-but-working request off. */
    public int $timeout = 180;

    public function __construct(public int $contentPieceId)
    {
    }

    public function handle(ClaudeContentService $claude): void
    {
        // withoutGlobalScopes for the same reason as RunSeoAudit - a
        // queued job has no authenticated user, so the tenant scope
        // would find nothing. The tenant is recorded on the row.
        $piece = ContentPiece::withoutGlobalScopes()->with('site')->find($this->contentPieceId);

        if (! $piece || $piece->status === 'completed') {
            return;
        }

        $piece->update(['status' => 'generating', 'started_at' => now()]);

        $result = $claude->generateArticle($piece->topic, $piece->site->name, $piece->site->url);

        $piece->update([
            'status' => $result['error'] && ! $result['body'] ? 'failed' : 'completed',
            'title' => $result['title'],
            'body' => $result['body'],
            'model' => $result['model'],
            'word_count' => $result['word_count'],
            'error' => $result['error'],
            'finished_at' => now(),
        ]);
    }

    /**
     * Same reasoning as RunSeoAudit::failed() - recorded so a piece
     * never sits in `generating` forever with no explanation.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Content generation job failed', ['content_piece_id' => $this->contentPieceId, 'error' => $e->getMessage()]);

        ContentPiece::withoutGlobalScopes()->where('id', $this->contentPieceId)->update([
            'status' => 'failed',
            'error' => 'The generation did not complete. This is a fault in SynthSEO rather than the topic itself.',
            'finished_at' => now(),
        ]);
    }
}
