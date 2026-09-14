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
     * Same title/body split mechanism as generateArticle - reused here
     * as subject/body rather than duplicating the marker-and-split
     * logic for what is structurally the same problem.
     *
     * @return array{subject:?string,body:?string,model:?string,error:?string}
     */
    public function generateNewsletter(string $topic, string $siteName, string $siteUrl): array
    {
        $empty = ['subject' => null, 'body' => null, 'model' => null, 'error' => null];

        $result = $this->callClaude($this->newsletterPrompt($topic, $siteName, $siteUrl), 1024);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        [$subject, $body] = $this->splitTitleAndBody($result['text']);

        return [
            'subject' => $subject,
            'body' => $body,
            'model' => self::MODEL,
            'error' => null,
        ];
    }

    /**
     * A short social caption tailored to one platform's actual voice,
     * not one generic paragraph reused everywhere. LinkedIn readers and
     * Instagram readers expect genuinely different things from the same
     * business - treating them identically reads as not having
     * bothered, which is worse than not posting at all.
     *
     * @return array{caption:?string,model:?string,error:?string}
     */
    public function generateSocialCaption(string $topic, string $platform, string $siteName, string $siteUrl): array
    {
        $empty = ['caption' => null, 'model' => null, 'error' => null];

        $result = $this->callClaude($this->socialCaptionPrompt($topic, $platform, $siteName, $siteUrl), 512);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        return ['caption' => trim($result['text']), 'model' => self::MODEL, 'error' => null];
    }

    /**
     * Reads a site's own homepage content and returns a short phrase
     * describing what the business actually does - "estate agents",
     * "garage MOT and servicing", "managed IT support" - suitable for
     * feeding straight into a search query. This is deliberately the
     * only thing inferred automatically here; a business's *location*
     * is set by hand on the site (see the migration's doc comment) for
     * the same reason `host` is - not reliably extractable from a
     * homepage, and Frankie already knows the answer.
     *
     * Kept to a strict few words on purpose: the caller builds a search
     * query as "{category} {location}", and a rambling category would
     * make a worse query than a precise one.
     *
     * @return array{category:?string,error:?string}
     */
    public function inferBusinessCategory(string $siteName, string $pageText): array
    {
        $empty = ['category' => null, 'error' => null];

        $result = $this->callClaude($this->businessCategoryPrompt($siteName, $pageText), 32);

        if ($result['error']) {
            return array_merge($empty, ['error' => $result['error']]);
        }

        $category = trim($result['text'], " \t\n\r\0\x0B\"'.");

        if ($category === '') {
            return array_merge($empty, ['error' => 'Could not determine what kind of business this site is for.']);
        }

        return ['category' => $category, 'error' => null];
    }

    /**
     * Turns an audit's failing/warning findings into a short, prioritised
     * plain-English action plan. Deliberately NOT run automatically with
     * every audit - see the migration's doc comment - so this is only
     * ever called once someone has actually asked for it.
     *
     * Written for genuinely zero technical background, not just
     * "plain English" - literal numbered steps (where to log in, what
     * to click) rather than advice that still assumes someone knows
     * how to find their way around a website admin panel. Many of the
     * people reading this have never done that and aren't developers
     * at all.
     *
     * $competitorContext, when present, is folded into the prompt so
     * Claude can weight advice against a real comparison ("you're
     * behind on X, which likely relates to this fix") rather than
     * generic advice in a vacuum. Optional because most audits will
     * not have a completed comparison to draw on yet.
     *
     * $stackContext, when present, tells Claude what platform the site
     * actually runs on (auto-detected CMS) and where it's hosted
     * (set by hand on the site, since hosting providers aren't
     * reliably detectable from outside) - the difference between
     * "enable caching" and "install WP Rocket, or ask your host to
     * enable server-side caching if you're not sure which plugin to
     * use." Optional because CMS detection can come back empty and
     * host is opt-in.
     *
     * @param array<int, array{title:string,detail:?string,status:string,severity:?string,source:string}> $findings
     * @param array{domain:string,ahead:bool,our_traffic:?int,competitor_traffic:?int,our_keywords:?int,competitor_keywords:?int}|null $competitorContext
     * @param array{cms:?string,host:?string}|null $stackContext
     * @return array{text:?string,model:?string,error:?string}
     */
    public function generateRecommendations(array $findings, string $siteName, string $siteUrl, ?array $competitorContext = null, ?array $stackContext = null): array
    {
        $empty = ['text' => null, 'model' => null, 'error' => null];

        if ($findings === []) {
            return array_merge($empty, ['error' => 'Nothing to summarise - this audit has no failing or warning findings.']);
        }

        // 1024 -> 1536: genuinely numbered, literal steps for 2-4 items
        // run longer than the old "plain paragraphs" version did, and a
        // walkthrough cut off mid-step is worse than a short one.
        $result = $this->callClaude($this->recommendationsPrompt($findings, $siteName, $siteUrl, $competitorContext, $stackContext), 1536);

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

    private function newsletterPrompt(string $topic, string $siteName, string $siteUrl): string
    {
        // Same TITLE: marker as articlePrompt, reused for the subject
        // line - splitTitleAndBody doesn't care what the marked line
        // means semantically, only that it is marked.
        return <<<PROMPT
        Write a short email newsletter for {$siteName} ({$siteUrl}) on the
        topic: "{$topic}"

        Write like a genuine update from the business owner to people
        who already know them, not a marketing campaign - warm, direct,
        no corporate newsletter filler ("we hope this email finds you
        well"). 150-250 words, plain paragraphs, no markdown, no bullet
        lists unless the content genuinely calls for one.

        Respond with the subject line on the first line as:
        TITLE: <the subject line>

        Then a blank line, then the newsletter body.
        PROMPT;
    }

    private function socialCaptionPrompt(string $topic, string $platform, string $siteName, string $siteUrl): string
    {
        // Platform voice as instructions, not just a label - "write for
        // LinkedIn" without saying what that actually means in practice
        // tends to produce the same generic paragraph as everywhere
        // else, just with the platform name substituted in.
        $voice = match ($platform) {
            'instagram' => 'Instagram: short, punchy, 2-4 lines max, a warm/conversational tone, light appropriate '
                . 'emoji use (not excessive), end with 3-5 relevant hashtags on their own line.',
            'linkedin' => 'LinkedIn: professional but not stiff, focused on the business value or expertise being '
                . 'shown, 3-5 sentences, no emoji, no hashtags unless one or two genuinely fit.',
            'facebook' => 'Facebook: friendly and conversational, like talking to a regular customer, 2-4 sentences, '
                . 'minimal or no hashtags.',
            default => 'General use across platforms: clear and friendly, 2-4 sentences, no heavy platform-specific '
                . 'styling (safe to reuse as-is on most platforms).',
        };

        return <<<PROMPT
        Write a social media post caption for {$siteName} ({$siteUrl}) about:
        "{$topic}"

        Platform and voice: {$voice}

        Write like a real local business owner, not a marketing
        department - specific and genuine, no generic filler like
        "check out our amazing services!". Output only the caption
        text itself, nothing else - no explanation, no options, no
        quotation marks around it.
        PROMPT;
    }

    private function businessCategoryPrompt(string $siteName, string $pageText): string
    {
        return <<<PROMPT
        Here is text from {$siteName}'s own website:

        {$pageText}

        In 2-5 words, what kind of business is this? Answer in the
        form a customer would type into Google when looking for this
        kind of business - e.g. "estate agents", "garage MOT and
        servicing", "managed IT support". Output only that phrase,
        nothing else - no explanation, no punctuation, no quotation
        marks.
        PROMPT;
    }

    private function recommendationsPrompt(array $findings, string $siteName, string $siteUrl, ?array $competitorContext = null, ?array $stackContext = null): string
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

        // Told as a sentence, not raw numbers - the model doesn't need
        // exact figures to weight its advice, and repeating unrounded
        // estimated-traffic numbers back in the recommendation text
        // would overstate a precision this data doesn't actually have.
        $competitorLine = '';

        if ($competitorContext) {
            $position = $competitorContext['ahead'] ? 'ahead of' : 'behind';
            $competitorLine = "\nFor context: compared to their competitor {$competitorContext['domain']}, "
                . "this business is currently {$position} on estimated search visibility and ranking keywords. "
                . "Weigh that when deciding what matters most, but do not quote specific traffic numbers back - "
                . "the findings above are the concrete, actionable detail.\n";
        }

        // Only mention what is actually known. A site with no detected
        // CMS gets no platform line at all rather than "this site's
        // platform is unknown" - a non-answer that would just read as
        // noise in front of a business owner.
        $stackLine = '';

        if ($stackContext && ($stackContext['cms'] || $stackContext['host'])) {
            $platform = trim(implode(' ', array_filter([
                $stackContext['cms'],
                $stackContext['host'] ? "hosted with {$stackContext['host']}" : null,
            ])));
            $stackLine = "\nThis site runs on {$platform}. Where a fix would genuinely differ by platform "
                . "(caching, image optimisation, a specific setting's location), give the concrete instruction for "
                . "this platform specifically rather than generic advice - name a real plugin or control panel "
                . "area if you are confident one applies, but do not invent a specific plugin name or menu path "
                . "you are not sure is accurate for this platform.\n"
                . "For anything about caching or a CDN specifically: many hosting providers already include their "
                . "own caching and CDN built into their control panel, which can conflict with or make redundant "
                . "a third-party caching plugin installed on top of it. You do not know what this specific host "
                . "includes, so before recommending a caching plugin, say to check the host's own control panel "
                . "for a built-in caching or CDN setting first, and only install a plugin if the host doesn't "
                . "already offer one.\n";
        }

        return <<<PROMPT
        You are advising the owner of {$siteName} ({$siteUrl}) on their
        website's SEO audit. Here are the issues found:

        {$findingsList}
        {$competitorLine}{$stackLine}
        Findings marked (source: lighthouse) come from Google Lighthouse
        tested on a simulated slow mobile connection, not a plain
        real-world load - a business owner testing the same URL in their
        own desktop browser on decent broadband will likely see it load
        much faster, and that is not a contradiction. If you state a
        specific time figure from one of these findings, say plainly
        that it reflects a simulated slow mobile connection rather than
        stating it as a flat, unqualified fact - otherwise it reads as
        wrong to someone who just tried the site themselves and saw it
        load fine.

        Write a prioritised action plan for someone who may never have
        used a website admin panel before and may not be a developer at
        all - assume genuinely zero technical background, not just
        "keep it simple." For each of the 2-4 things that matter most:

        - One plain sentence on what it is and why it matters. Explain
          any jargon the first time you use it (a "meta description",
          an "H1 heading") rather than assuming it's already understood.
        - Then literal, numbered steps to actually do it: where to log
          in, which exact menu or button to click, what to search for
          or type. If the platform is known, name the real menu, button,
          or plugin - do not invent one you are not confident is
          accurate for that platform. If the platform is not known, say
          plainly to ask whoever manages the website to make this change,
          rather than guessing at menus that might not exist for them.

        Skip anything trivial. No markdown headers, but numbered steps
        within each item are expected, not optional - that literal
        walkthrough is the actual point of this. Long enough to really
        walk someone through it; not padded with filler.
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
