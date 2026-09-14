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

    /** Most failing audits a single report should carry. Lighthouse
     *  emits dozens; past this many they stop being a to-do list and
     *  start being wallpaper. Worst-scoring first. */
    private const MAX_FINDINGS = 8;

    /** Audits that describe a measurement rather than something to go
     *  and fix. These are already shown as the Core Web Vitals row, so
     *  repeating them as "issues" would double-count one problem. */
    private const METRIC_AUDITS = [
        'largest-contentful-paint', 'cumulative-layout-shift', 'total-blocking-time',
        'first-contentful-paint', 'speed-index', 'interactive', 'max-potential-fid',
        'first-meaningful-paint', 'estimated-input-latency',
    ];

    /**
     * Recognised image file extensions, checked against each details.item's
     * url - not which audit reported it. The first version of this
     * gated on a hardcoded list of "known image audit" keys
     * (properly-sized-images, modern-image-formats, etc.), which broke
     * on the very first real site tested: Lighthouse 13 merged all of
     * those into a single image-delivery-insight audit, an ID that
     * didn't exist when this was written. findings() itself already
     * avoids exactly this trap by never hardcoding audit keys (see its
     * own doc comment) - this enrichment now follows the same rule,
     * checking what the flagged resource actually is rather than what
     * Google currently calls the check that flagged it. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    /** Images captured per finding. A page can have dozens of
     *  offending images; four real examples make the point without
     *  the audit page turning into a gallery. */
    private const MAX_IMAGES_PER_FINDING = 4;

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

        // PSI expects `category` REPEATED once per value:
        //   ?category=performance&category=seo&...
        // Passing an array to Laravel's HTTP client serialises it as
        // category[0]=...&category[1]=..., which Google silently ignores
        // and falls back to performance only. That is exactly what
        // happened on the first real run: Performance came back 69 and
        // the other three were empty, looking like a Google fault when
        // it was ours. Built by hand so the shape is unambiguous.
        $params = [
            'url=' . rawurlencode($url),
            'strategy=' . rawurlencode($strategy),
        ];

        foreach (['performance', 'seo', 'accessibility', 'best-practices'] as $category) {
            $params[] = 'category=' . rawurlencode($category);
        }

        if ($this->apiKey) {
            $params[] = 'key=' . rawurlencode($this->apiKey);
        }

        $endpoint = self::ENDPOINT . '?' . implode('&', $params);

        try {
            $response = Http::timeout(self::TIMEOUT)->get($endpoint);
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
     * Turns Lighthouse's failing audits into findings in our own shape.
     *
     * DELIBERATELY DOES NOT HARDCODE AUDIT KEYS. The first version did,
     * with a curated list like 'render-blocking-resources', and it
     * silently produced nothing: Lighthouse 13 replaced "opportunities"
     * with "insights" and renamed the keys, so every lookup missed. The
     * failure mode was the worst kind - a performance score of 69 with
     * an empty issue list, which reads as "no problems found" rather
     * than "this code is broken".
     *
     * So instead it walks the performance category's own auditRefs and
     * takes whatever is failing, using Lighthouse's own titles. That
     * survives Google renaming things, which they will do again.
     */
    private function findings(array $lh): array
    {
        $audits = $lh['audits'] ?? [];
        $refs = $lh['categories']['performance']['auditRefs'] ?? [];

        if (! is_array($audits) || ! is_array($refs)) {
            return [];
        }

        $candidates = [];

        foreach ($refs as $ref) {
            $key = is_array($ref) ? ($ref['id'] ?? null) : null;

            if (! $key || ! isset($audits[$key]) || ! is_array($audits[$key])) {
                continue;
            }

            $audit = $audits[$key];
            $score = $audit['score'] ?? null;
            $mode = $audit['scoreDisplayMode'] ?? 'numeric';

            // informative / notApplicable / manual audits carry no
            // verdict - listing them as issues would be inventing one.
            if (in_array($mode, ['informative', 'notApplicable', 'manual'], true)) {
                continue;
            }

            // Passing audits are skipped, unlike our own checks where
            // recording the pass is the point. Forty green Lighthouse
            // rows would bury the findings that matter.
            if (! is_numeric($score) || $score >= 0.9) {
                continue;
            }

            if (in_array($key, self::METRIC_AUDITS, true)) {
                continue;
            }

            $title = $audit['title'] ?? null;

            if (! $title) {
                continue;
            }

            $candidates[] = [
                'score' => (float) $score,
                'finding' => [
                    'source' => 'lighthouse',
                    'check' => $key,
                    'status' => $score < 0.5 ? 'fail' : 'warn',
                    'severity' => $score < 0.5 ? 'medium' : 'low',
                    'title' => $title,
                    'detail' => $this->cleanDescription($audit['description'] ?? null),
                    'value' => $audit['displayValue'] ?? null,
                    'images' => $this->extractImages($audit),
                ],
            ];
        }

        // Worst first, so the cap keeps the things most worth doing.
        usort($candidates, fn ($a, $b) => $a['score'] <=> $b['score']);

        return array_column(array_slice($candidates, 0, self::MAX_FINDINGS), 'finding');
    }

    /**
     * Pulls specific offending images (url, and whatever size/savings
     * fields are present) out of one audit's details.items table.
     * Defensive about field names on purpose - Lighthouse's per-item
     * byte fields aren't perfectly consistent across every image
     * audit, and only 'url' is ever required. An item with no url at
     * all (some audits list a DOM node instead of a resource) is
     * silently skipped rather than guessed at.
     *
     * @return array<int, array{url:string,wasted_bytes:?int,total_bytes:?int}>|null
     */
    /**
     * Pulls specific offending images (url, and whatever size/savings
     * fields are present) out of one audit's details.items table -
     * whichever audit that happens to be. See IMAGE_EXTENSIONS' own
     * doc comment for why this checks the resource itself rather than
     * which audit reported it.
     *
     * Defensive about field names on purpose beyond that: Lighthouse's
     * per-item byte fields aren't perfectly consistent across every
     * audit, and only 'url' (plus a recognised image extension) is
     * ever required.
     *
     * @return array<int, array{url:string,wasted_bytes:?int,total_bytes:?int}>|null
     */
    private function extractImages(array $audit): ?array
    {
        $items = $audit['details']['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        $images = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            // Some audits nest the actual resource under a 'node' or
            // 'subItems' structure rather than a flat url - only the
            // flat, common shape is handled here. A miss just means
            // no thumbnail for that particular item, not a crash.
            $url = $item['url'] ?? null;

            if (! is_string($url) || ! preg_match('#^https?://#i', $url)) {
                continue;
            }

            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

            if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }

            $images[] = [
                'url' => $url,
                'wasted_bytes' => isset($item['wastedBytes']) ? (int) round($item['wastedBytes']) : null,
                'total_bytes' => isset($item['totalBytes']) ? (int) round($item['totalBytes']) : null,
            ];

            if (count($images) >= self::MAX_IMAGES_PER_FINDING) {
                break;
            }
        }

        return $images === [] ? null : $images;
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
