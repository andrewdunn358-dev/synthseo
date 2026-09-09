<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates a draft article via the Claude API (Anthropic Messages
 * endpoint), scoped to one site's context.
 *
 * WHY A SEPARATE SERVICE RATHER THAN INLINE IN THE JOB
 * Same reasoning as PageSpeedService: this is the one place that knows
 * the request shape, the model name, and how to turn a failure into a
 * sentence a client can read. Keeping that out of the job means the job
 * stays about lifecycle (queued/generating/completed/failed), not about
 * an HTTP contract that will change under us.
 *
 * FAILURE IS NORMAL AND MUST NOT THROW
 * A bad API key, a rate limit, a timeout on a slow day - none of these
 * are exceptional here, they are Tuesday. This returns an error string
 * and a null body rather than throwing, exactly like PageSpeedService,
 * so a generation failure is a row that says why, not a queue worker
 * that silently stops.
 *
 * WHY word_count over token usage
 * The API response includes token usage, but a client asking "how much
 * did this cost" is answered by usage.output_tokens if that is ever
 * needed - word_count is what a human reviewing a draft actually wants
 * to see at a glance, so that is what gets stored on the row.
 */
class ClaudeContentService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    /** Model chosen for cost/quality on a blog-length draft, not the
     *  biggest available. Content generation runs per client per month,
     *  not once - this has to stay cheap at volume. */
    private const MODEL = 'claude-sonnet-4-5';

    /** Long-form article generation genuinely takes 20-60s. Shorter
     *  than PageSpeed's 90s because there is no third-party crawl
     *  behind this, just the model itself. */
    private const TIMEOUT = 120;

    public function __construct(private ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.anthropic.key');
    }

    /**
     * @return array{title:?string,body:?string,word_count:?int,model:?string,error:?string}
     */
    public function generateArticle(string $topic, string $siteName, string $siteUrl): array
    {
        $empty = ['title' => null, 'body' => null, 'word_count' => null, 'model' => null, 'error' => null];

        if (! $this->apiKey) {
            return array_merge($empty, [
                'error' => 'No Claude API key configured. Add ANTHROPIC_API_KEY in .env to enable content generation.',
            ]);
        }

        $prompt = $this->buildPrompt($topic, $siteName, $siteUrl);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ])
                ->timeout(self::TIMEOUT)
                ->post(self::ENDPOINT, [
                    'model' => self::MODEL,
                    'max_tokens' => 2048,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Claude content request failed', ['topic' => $topic, 'error' => $e->getMessage()]);

            return array_merge($empty, [
                'error' => 'The content request did not complete (it may have timed out). Try running it again.',
            ]);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->readableError($response->status(), $response->json())]);
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || trim($text) === '') {
            return array_merge($empty, ['error' => 'Claude returned an empty response for this topic.']);
        }

        [$title, $body] = $this->splitTitleAndBody($text);

        return [
            'title' => $title,
            'body' => $body,
            'word_count' => str_word_count($body),
            'model' => self::MODEL,
            'error' => null,
        ];
    }

    private function buildPrompt(string $topic, string $siteName, string $siteUrl): string
    {
        // Asking for a marked title line rather than JSON: a model
        // asked for JSON around a long-form article will sometimes
        // escape it inconsistently across paragraphs. A plain marker
        // splits reliably with one line of PHP and never mangles the
        // prose either side of it.
        return <<<PROMPT
        Write a blog article for {$siteName} ({$siteUrl}) on the topic:
        "{$topic}"

        Write for a local customer researching this before deciding who
        to use - clear, specific, no filler, no generic SEO padding.
        Aim for 500-700 words.

        Respond with the title on the first line as:
        TITLE: <the title>

        Then a blank line, then the article body in plain paragraphs
        (no markdown headers, no bullet lists unless the content
        genuinely calls for a short list).
        PROMPT;
    }

    private function splitTitleAndBody(string $text): array
    {
        $text = trim($text);

        if (preg_match('/^TITLE:\s*(.+?)\s*\n+(.*)$/s', $text, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        // Model didn't follow the marker - use the first line as the
        // title rather than fail a generation that otherwise worked.
        $lines = explode("\n", $text, 2);

        return [trim($lines[0]), trim($lines[1] ?? $text)];
    }

    private function readableError(int $status, ?array $body): string
    {
        $message = $body['error']['message'] ?? '';

        return match (true) {
            $status === 401 => 'The Claude API key was rejected. Check ANTHROPIC_API_KEY in .env.',
            $status === 429 => 'Claude rate-limited the request. Wait a moment and try again.',
            $status === 400 => 'Claude rejected the request' . ($message ? ': ' . mb_substr($message, 0, 200) : '.'),
            $status >= 500 => 'Claude is temporarily unavailable. Try again shortly.',
            default => "Claude returned HTTP {$status}.",
        };
    }
}
