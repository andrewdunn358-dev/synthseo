<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google PageSpeed Insights — runs Lighthouse against a URL and returns
 * the four category scores, the Core Web Vitals, and the opportunities
 * worth acting on.
 *
 * WHY THIS EXISTS RATHER THAN BUILDING IT
 * Performance and accessibility auditing needs a real browser: a
 * headless Chrome, network throttling, a rendered layout to measure
 * shift against. None of that runs on 20i shared hosting, and
 * approximating it from raw HTML would produce numbers that look
 * authoritative and mean nothing.
 *
 * PSI is free, needs no browser at our end, and is Google's own
 * measurement - which for a client report is worth more than anything
 * we could compute ourselves. A Torque report saying "Google scores
 * your performance 86" is a different conversation from "our tool
 * scores you 86".
 *
 * This is deliberately NOT a replacement for SeoAuditService. Lighthouse
 * checks a narrow, deliberately basic SEO set - it gave 100 to a page
 * our own checks found real issues on. The two answer different
 * questions and the report shows both, labelled.
 *
 * FAILURE IS NORMAL AND MUST NOT BREAK THE AUDIT
 * PSI rate-limits hard without a key, times out on slow sites, and
 * cannot reach anything behind auth or a firewall. Every one of those
 * is a routine outcome, not an exception - so this returns an error
 * string and nulls rather than throwing, and the audit completes with
 * its own findings intact.
 */
class PageSpeedService
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /** Lighthouse itself takes 10-30s on a slow site. This has to be
     *  generous or we abandon runs that would have succeeded. */
    private const TIMEOUT = 90;

    /** Opportunities worth surfacing to a client, with the wording we
     *  use for them. Lighthouse emits dozens; most are noise in a
     *  client-facing report. This is the shortlist that maps to
     *  something a person can actually go and do. */
    private const OPPORTUNITIES = [
        'render-blocking-resources' => 'Render-blocking requests are delaying first paint',
        'uses-responsive-images' => 'Images are larger than they need to be',
        'unused-css-rules' => 'Unused CSS is being downloaded',
        'unused-javascript' => 'Unused JavaScript is being downloaded',
        'uses-long-cache-ttl' => 'Static assets are not cached for long enough',
        'uses-text-compression' => 'Text assets are not compressed',
        'modern-image-formats' => 'Images are not in a modern format (WebP or AVIF)',
        'server-response-time' => 'The server is slow to send the first byte',
        'total-byte-weight' => 'The page is very heavy to download',
    ];

    public function __construct(private ?string $apiKey = null)
    {
        // Optional. Without one PSI still answers, but rate-limits
        // aggressively - fine for testing, not for running audits across
        // a client base. Configured in .env as PAGESPEED_API_KEY.
        $this->apiKey = $apiKey ?? config('services.pagespeed.key');
    }

    /**
     * @return array{scores:array,metrics:array,final_url:?string,strategy:string,findings:array,error:?string}
     */
    public function run(string $url, string $strategy = 'mobile'): array
    {
        $empty = [
            'scores' => [], 'metrics' => [], 'final_url' => null,
            'strategy' => $strategy, 'findings' => [], 'error' => null,
        ];

        $query = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => ['performance', 'seo', 'accessibility', 'best-practices'],
        ];

        if ($this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::ENDPOINT, $query);
        } catch (\Throwable $e) {
            Log::warning('PageSpeed request failed', ['url' => $url, 'error' => $e->getMessage()]);
            return array_merge($empty, [
                'error' => 'The PageSpeed request did not complete (it may have timed out). The rest of this audit is unaffected.',
            ]);
        }

        if (! $response->successful()) {
            return array_merge($empty, ['error' => $this->readableError($response->status(), $response->json())]);
        }

        $lh = $response->json('lighthouseResult');

        if (! is_array($lh)) {
            return array_merge($empty, ['error' => 'PageSpeed returned no Lighthouse result for this URL.']);
        }

        return [
            'scores' => $this->scores($lh),
            'metrics' => $this->metrics($lh),
            'final_url' => $lh['finalUrl'] ?? $lh['requestedUrl'] ?? null,
            'strategy' => $strategy,
            'findings' => $this->findings($lh),
            'error' => null,
        ];
    }

    /** Lighthouse scores are 0-1 floats; everyone reads them as 0-100. */
    private function scores(array $lh): array
    {
        $out = [];

        foreach (['performance', 'seo', 'accessibility', 'best-practices'] as $category) {
            $score = $lh['categories'][$category]['score'] ?? null;
            // A null score is a category Lighthouse could not run - kept
            // as null, never coerced to 0. See the migration note.
            $out[$category] = is_numeric($score) ? (int) round($score * 100) : null;
        }

        return $out;
    }

    private function metrics(array $lh): array
    {
        $audits = $lh['audits'] ?? [];

        return [
            'lcp_ms' => isset($audits['largest-contentful-paint']['numericValue'])
                ? (int) round($audits['largest-contentful-paint']['numericValue']) : null,
            'tbt_ms' => isset($audits['total-blocking-time']['numericValue'])
                ? (int) round($audits['total-blocking-time']['numericValue']) : null,
            'cls' => isset($audits['cumulative-layout-shift']['numericValue'])
                ? round((float) $audits['cumulative-layout-shift']['numericValue'], 3) : null,
        ];
    }

    /**
     * Turns Lighthouse's failing audits into findings in our own shape,
     * so one report can show both engines' output in one list.
     */
    private function findings(array $lh): array
    {
        $audits = $lh['audits'] ?? [];
        $findings = [];

        foreach (self::OPPORTUNITIES as $key => $title) {
            $audit = $audits[$key] ?? null;

            if (! is_array($audit)) {
                continue;
            }

            $score = $audit['score'] ?? null;

            // Lighthouse marks a passing opportunity with score 1. Only
            // the ones it actually flags are worth a client's attention,
            // so passes are skipped here - unlike our own checks, where
            // recording the pass is the point. Listing forty green
            // Lighthouse rows would bury our own findings.
            if (! is_numeric($score) || $score >= 0.9) {
                continue;
            }

            $findings[] = [
                'source' => 'lighthouse',
                'check' => $key,
                'status' => $score < 0.5 ? 'fail' : 'warn',
                'severity' => $score < 0.5 ? 'medium' : 'low',
                'title' => $title,
                'detail' => $this->cleanDescription($audit['description'] ?? null),
                'value' => $audit['displayValue'] ?? null,
            ];
        }

        return $findings;
    }

    /**
     * Lighthouse descriptions are markdown with documentation links.
     * Strips the link syntax but keeps the text, so a client reads a
     * sentence rather than a URL soup.
     */
    private function cleanDescription(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);

        return trim(mb_substr($text, 0, 400));
    }

    private function readableError(int $status, ?array $body): string
    {
        $message = $body['error']['message'] ?? '';

        return match (true) {
            $status === 429 => 'Google rate-limited the request. Adding a PageSpeed API key in Settings raises the limit substantially.',
            $status === 400 && str_contains($message, 'FAILED_DOCUMENT_REQUEST')
                => 'Google could not load the page. This usually means the site blocked it, or is not reachable from the public internet.',
            $status === 400 => 'Google rejected the request for this URL' . ($message ? ': ' . mb_substr($message, 0, 200) : '.'),
            $status === 403 => 'The PageSpeed API key was rejected. Check it is valid and that the PageSpeed Insights API is enabled for it.',
            $status >= 500 => 'Google PageSpeed is temporarily unavailable. The rest of this audit is unaffected.',
            default => "Google PageSpeed returned HTTP {$status}.",
        };
    }
}
