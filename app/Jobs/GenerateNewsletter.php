<?php

namespace App\Jobs;

use App\Models\Newsletter;
use App\Services\ClaudeContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates one newsletter draft. Sending is a deliberately separate
 * step - see the migration's doc comment - so this job only ever
 * writes a draft for a person to review, never sends anything.
 */
class GenerateNewsletter implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $newsletterId)
    {
    }

    public function handle(ClaudeContentService $claude): void
    {
        // withoutGlobalScopes for the same reason as every other job -
        // no authenticated user in a queue worker.
        $newsletter = Newsletter::withoutGlobalScopes()->with('site')->find($this->newsletterId);

        if (! $newsletter || $newsletter->status === 'completed') {
            return;
        }

        $newsletter->update(['status' => 'generating']);

        $result = $claude->generateNewsletter($newsletter->topic, $newsletter->site->name, $newsletter->site->url);

        $newsletter->update([
            'status' => $result['error'] && ! $result['body'] ? 'failed' : 'completed',
            'subject' => $result['subject'],
            'body' => $result['body'],
            'model' => $result['model'],
            'error' => $result['error'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Newsletter generation job failed', ['newsletter_id' => $this->newsletterId, 'error' => $e->getMessage()]);

        Newsletter::withoutGlobalScopes()->where('id', $this->newsletterId)->update([
            'status' => 'failed',
            'error' => 'The generation did not complete. This is a fault in SynthSEO rather than the topic itself.',
        ]);
    }
}
