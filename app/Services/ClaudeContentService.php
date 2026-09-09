<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Everything that talks to the Claude API (Anthropic Messages
 * endpoint) - article drafts and, now, plain-English recommendations
 * built from a completed audit's findings.
 *
 * WHY ONE SERVICE FOR BOTH RATHER THAN TWO
 * Both are "send a prompt, get prose back" - the only difference is the
 * prompt and what happens to the text afterwards. Splitting them would
 * duplicate the HTTP call, the timeout, the error handling, and the
 * five ways this can fail (bad key, rate limit, timeout, empty
 * response, non-2xx) - exactly the trap PageSpeedService avoided by
 * being the one place that knows the PSI contract. callClaude() is that
 * one place here.
 *
 * FAILURE IS NORMAL AND MUST NOT THROW
 * A bad API key, a rate limit, a timeout on a slow day - none of these
 * are exceptional here, they are Tuesday. Every public method returns
 * an error string and null content rather than throwing, so a failure
 * is a row that says why, not a queue worker that silently stops.
 */
class ClaudeContentService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    /** Model chosen for cost/quality at volume, not the biggest
     *  available - this runs per client, repeatedly, not once. */
    private const MODEL = 'claude-sonnet-4-5';

    /** Long-form generation genuinely takes 20-60s. Shorter than
     *  PageSpeed's 90s because there is no third-party crawl behind
     *  this, just the model itself. */
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

        $result = $this->callClaude($this->articlePrompt($topic, $siteName, $siteUrl), 2048);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        [$title, $body] = $this->splitTitleAndBody($result['text']);

        return [
            'title' => $title,
            'body' => $body,
            'word_count' => str_word_count($body),
            'model' => self::MODEL,
            'error' => null,
        ];
    }

    /**
     * Turns an audit's failing/warning findings into a short, prioritised
     * plain-English action plan. Deliberately NOT run automatically with
     * every audit - see the migration's doc comment - so this is only
     * ever called once someone has actually asked for it.
     *
     * @param array<int, array{title:string,detail:?string,status:string,severity:?string,source:string}> $findings
     * @return array{text:?string,model:?string,error:?string}
     */
    public function generateRecommendations(array $findings, string $siteName, string $siteUrl): array
    {
        $empty = ['text' => null, 'model' => null, 'error' => null];

        if ($findings === []) {
            return array_merge($empty, ['error' => 'Nothing to summarise - this audit has no failing or warning findings.']);
        }

        $result = $this->callClaude($this->recommendationsPrompt($findings, $siteName, $siteUrl), 1024);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        return ['text' => trim($result['text']), 'model' => self::MODEL, 'error' => null];
    }

    /**
     * The one place that actually calls the API. Both public methods
     * above go through this, so a change to auth, timeout, or error
     * handling only has to happen once.
     *
     * @return array{text:?string,error:?string}
     */
    private function callClaude(string $prompt, int $maxTokens): array
    {
        if (! $this->apiKey) {
            return ['text' => null, 'error' => 'No Claude API key configured. Add ANTHROPIC_API_KEY in .env to enable this.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ])
                ->timeout(self::TIMEOUT)
                ->post(self::ENDPOINT, [
                    'model' => self::MODEL,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Claude request failed', ['error' => $e->getMessage()]);

            return ['text' => null, 'error' => 'The request did not complete (it may have timed out). Try again.'];
        }

        if (! $response->successful()) {
            return ['text' => null, 'error' => $this->readableError($response->status(), $response->json())];
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || trim($text) === '') {
            return ['text' => null, 'error' => 'Claude returned an empty response.'];
        }

        return ['text' => $text, 'error' => null];
    }

    private function articlePrompt(string $topic, string $siteName, string $siteUrl): string
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

    private function recommendationsPrompt(array $findings, string $siteName, string $siteUrl): string
    {
        // Findings serialised as plain lines rather than JSON - the
        // model doesn't need machine-readable input, and a flat list
        // keeps the prompt short, which matters at real volume.
        $lines = array_map(
            fn (array $f) => sprintf(
                '- [%s/%s] %s%s (source: %s)',
                strtoupper($f['status']),
                $f['severity'] ?? 'n/a',
                $f['title'],
                $f['detail'] ? ' — ' . $f['detail'] : '',
                $f['source'],
            ),
            $findings,
        );

        $findingsList = implode("\n", $lines);

        return <<<PROMPT
        You are advising the owner of {$siteName} ({$siteUrl}) on their
        website's SEO audit. Here are the issues found:

        {$findingsList}

        Write a short, prioritised action plan a non-technical business
        owner can actually use: which 2-4 things matter most and why,
        in plain English, no jargon left unexplained. Skip anything
        trivial. No headers, no markdown - plain paragraphs, under 250
        words.
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
