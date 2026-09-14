<?php

namespace App\Jobs;

use App\Models\SocialPost;
use App\Services\ClaudeContentService;
use App\Services\OpenAiImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates one social post's caption and image.
 *
 * Caption and image are independent outcomes, not one pass/fail - see
 * the social_posts migration's doc comment for why. Both are attempted
 * even if one fails first: a client still gets a usable caption if the
 * image generation has a bad day, and vice versa. Overall status is
 * only 'failed' when both are.
 */
class GenerateSocialPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Generous - captions are quick, but image generation alone can
     *  take up to 90s, and this job does both in sequence. */
    public int $timeout = 180;

    public function __construct(public int $postId)
    {
    }

    public function handle(ClaudeContentService $claude, OpenAiImageService $images): void
    {
        // withoutGlobalScopes for the same reason as every other job -
        // no authenticated user in a queue worker.
        $post = SocialPost::withoutGlobalScopes()->with('site')->find($this->postId);

        if (! $post || $post->status === 'completed') {
            return;
        }

        $post->update(['status' => 'generating', 'started_at' => now()]);

        $caption = $claude->generateSocialCaption($post->topic, $post->platform, $post->site->name, $post->site->url);

        // The image prompt is built from the topic directly rather than
        // the generated caption - the caption can fail independently,
        // and an image prompt should not depend on a text generation
        // that might not have succeeded.
        $imagePrompt = "A clean, professional marketing photo for a local business post about: {$post->topic}. "
            . "Realistic, warm, inviting. No text, no words, no logos, no watermarks in the image.";

        $image = $images->generateImage($imagePrompt);

        $post->update([
            'status' => ($caption['error'] && ! $caption['caption']) && ($image['error'] && ! $image['path'])
                ? 'failed' : 'completed',
            'caption' => $caption['caption'],
            'caption_error' => $caption['error'],
            'image_path' => $image['path'],
            'image_error' => $image['error'],
            'model' => $caption['model'],
            'finished_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Social post generation job failed', ['post_id' => $this->postId, 'error' => $e->getMessage()]);

        SocialPost::withoutGlobalScopes()->where('id', $this->postId)->update([
            'status' => 'failed',
            'caption_error' => 'The generation did not complete. This is a fault in SynthSEO rather than the topic itself.',
            'finished_at' => now(),
        ]);
    }
}
