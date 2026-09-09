<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

/**
 * The on-page SEO audit engine.
 *
 * Fetches one URL and runs a fixed set of checks against it, returning
 * a score and a list of findings. No external service, no API key, no
 * per-check cost - this is the part of the product that must work for
 * every client on every run, so it is built in-house and depends on
 * nothing but PHP's own DOM extension. Keyword ranks and backlinks are
 * a different problem and belong with a paid data provider; those are
 * genuinely not derivable from fetching a page.
 *
 * WHY THE CHECKS ARE DATA, NOT CODE
 * Each check returns a finding with a status, a severity and the value
 * it actually measured. Both passes and failures are recorded. A report
 * that only lists problems cannot tell a client the difference between
 * "your titles are fine" and "we never looked at your titles", and that
 * distinction is exactly what someone paying for an audit is buying.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 * It does not render JavaScript. A headless browser on shared hosting
 * is not viable, and pretending otherwise would silently under-report
 * on SPA sites. Where that matters the report should say so rather than
 * scoring a JS-rendered page as empty - see the thin-content check,
 * which flags low word count as a warning rather than a failure for
 * exactly this reason.
 *
 * It does not crawl. One URL per audit for now. Multi-page crawling
 * changes the cost and time profile completely (and needs politeness,
 * concurrency limits and robots parsing), so it is a separate piece of
 * work rather than something bolted onto this quietly.
 */
class SeoAuditService
{
    /** Seconds before a fetch is abandoned. A client site that takes
     *  longer than this to answer has a problem worth reporting in
     *  itself, so the timeout is short and the failure is a finding
     *  rather than an exception. */
    private const TIMEOUT = 20;

    /** Penalty applied to the score per failed check, by severity.
     *  Warnings cost half. These are judgement calls, kept in one place
     *  so the scoring can be re-tuned without hunting through checks. */
    private const WEIGHTS = ['high' => 15, 'medium' => 8, 'low' => 3];

    /**
     * @return array{score:int|null,http_status:int|null,response_ms:int|null,error:string|null,findings:array}
     */
    public function run(string $url): array
    {
        $started = microtime(true);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => 'SynthSEO-Audit/1.0 (+https://synthseo.co.uk)'])
                ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                ->get($url);
        } catch (\Throwable $e) {
            // A site that cannot be reached is a real, reportable
            // result - not a crash. The audit records why and scores
            // nothing rather than scoring zero, because "unreachable"
            // and "reachable but terrible" are different answers.
            return [
                'score' => null,
                'http_status' => null,
                'response_ms' => (int) ((microtime(true) - $started) * 1000),
                'error' => $this->readableError($e->getMessage()),
                'findings' => [],
            ];
        }

        $responseMs = (int) ((microtime(true) - $started) * 1000);
        $html = $response->body();
        $findings = [];

        $findings[] = $this->checkHttpStatus($response->status());
        $findings[] = $this->checkHttps($url);
        $findings[] = $this->checkResponseTime($responseMs);

        if (! $response->successful()) {
            // No point parsing an error page's markup and reporting its
            // missing meta description as if it were the client's
            // homepage. Stop here and say so.
            return [
                'score' => $this->score($findings),
                'http_status' => $response->status(),
                'response_ms' => $responseMs,
                'error' => 'The page returned HTTP ' . $response->status() . ', so on-page checks were skipped.',
                'findings' => $findings,
            ];
        }

        $xpath = $this->parse($html);

        $findings[] = $this->checkTitle($xpath);
        $findings[] = $this->checkMetaDescription($xpath);
        $findings[] = $this->checkH1($xpath);
        $findings[] = $this->checkCanonical($xpath);
        $findings[] = $this->checkViewport($xpath);
        $findings[] = $this->checkLang($xpath);
        $findings[] = $this->checkImageAlts($xpath);
        $findings[] = $this->checkIndexable($xpath);
        $findings[] = $this->checkOpenGraph($xpath);
        $findings[] = $this->checkHeadingOrder($xpath);
        $findings[] = $this->checkWordCount($xpath);
        $findings[] = $this->checkRobotsTxt($url);
        $findings[] = $this->checkSitemap($url);

        return [
            'score' => $this->score($findings),
            'http_status' => $response->status(),
            'response_ms' => $responseMs,
            'error' => null,
            'findings' => $findings,
        ];
    }

    // ------------------------------------------------------------ checks

    private function checkHttpStatus(int $status): array
    {
        return $status >= 200 && $status < 300
            ? $this->pass('http_status', 'Page loads successfully', "HTTP {$status}")
            : $this->fail('http_status', 'high', 'Page did not load successfully',
                "The URL returned HTTP {$status}. Search engines cannot index a page they cannot fetch.", "HTTP {$status}");
    }

    private function checkHttps(string $url): array
    {
        return str_starts_with(strtolower($url), 'https://')
            ? $this->pass('https', 'Served over HTTPS', 'https')
            : $this->fail('https', 'high', 'Not served over HTTPS',
                'HTTPS is a ranking signal and browsers mark plain HTTP pages as not secure.', 'http');
    }

    private function checkResponseTime(int $ms): array
    {
        if ($ms <= 1200) {
            return $this->pass('response_time', 'Server responded quickly', "{$ms} ms");
        }

        // Server response time only - not full page load. Labelled that
        // way in the detail so nobody reads this as a Core Web Vitals
        // number, which it is not.
        return $ms <= 3000
            ? $this->warn('response_time', 'medium', 'Server response is slow',
                "The server took {$ms} ms to respond. This is server time only, not full page load.", "{$ms} ms")
            : $this->fail('response_time', 'medium', 'Server response is very slow',
                "The server took {$ms} ms to respond. This is server time only, not full page load.", "{$ms} ms");
    }

    private function checkTitle(DOMXPath $xpath): array
    {
        $title = $this->text($xpath, '//head/title');

        if ($title === '') {
            return $this->fail('title', 'high', 'Missing page title',
                'The page has no <title>. It is the single strongest on-page signal and the headline in search results.', null);
        }

        $len = mb_strlen($title);

        if ($len < 30) {
            return $this->warn('title', 'medium', 'Page title is short',
                "The title is {$len} characters. Around 30-60 gives room to describe the page without being truncated.", $title);
        }

        if ($len > 60) {
            return $this->warn('title', 'low', 'Page title may be truncated',
                "The title is {$len} characters. Google typically truncates around 60.", $title);
        }

        return $this->pass('title', 'Page title is present and a sensible length', $title);
    }

    private function checkMetaDescription(DOMXPath $xpath): array
    {
        $desc = $this->attr($xpath, '//meta[translate(@name,"DESCRIPTION","description")="description"]/@content');

        if ($desc === '') {
            return $this->fail('meta_description', 'medium', 'Missing meta description',
                'Without one, search engines invent a snippet from page text. Writing it yourself controls the click-through pitch.', null);
        }

        $len = mb_strlen($desc);

        if ($len < 70 || $len > 160) {
            return $this->warn('meta_description', 'low', 'Meta description length is outside the useful range',
                "The description is {$len} characters. Around 70-160 displays fully in most results.", $desc);
        }

        return $this->pass('meta_description', 'Meta description is present and a sensible length', $desc);
    }

    private function checkH1(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//h1');
        $count = $nodes ? $nodes->length : 0;

        if ($count === 0) {
            return $this->fail('h1', 'medium', 'No H1 heading',
                'The H1 tells both readers and search engines what the page is about.', null);
        }

        if ($count > 1) {
            return $this->warn('h1', 'low', 'More than one H1 heading',
                "Found {$count} H1 headings. One clear primary heading is easier to interpret.", (string) $count . ' found');
        }

        return $this->pass('h1', 'Exactly one H1 heading', trim($nodes->item(0)->textContent));
    }

    private function checkCanonical(DOMXPath $xpath): array
    {
        $canonical = $this->attr($xpath, '//link[@rel="canonical"]/@href');

        return $canonical !== ''
            ? $this->pass('canonical', 'Canonical URL is set', $canonical)
            : $this->warn('canonical', 'medium', 'No canonical URL',
                'A canonical tag tells search engines which version of a page to index, and prevents duplicate-content dilution.', null);
    }

    private function checkViewport(DOMXPath $xpath): array
    {
        $viewport = $this->attr($xpath, '//meta[@name="viewport"]/@content');

        return $viewport !== ''
            ? $this->pass('viewport', 'Mobile viewport is set', $viewport)
            : $this->fail('viewport', 'high', 'No mobile viewport tag',
                'Without a viewport meta tag the page will not scale correctly on phones. Google indexes the mobile version first.', null);
    }

    private function checkLang(DOMXPath $xpath): array
    {
        $lang = $this->attr($xpath, '//html/@lang');

        return $lang !== ''
            ? $this->pass('lang', 'Page language is declared', $lang)
            : $this->warn('lang', 'low', 'No language attribute on <html>',
                'Declaring the language helps search engines serve the page to the right audience, and screen readers pronounce it correctly.', null);
    }

    private function checkImageAlts(DOMXPath $xpath): array
    {
        $all = $xpath->query('//img');
        $total = $all ? $all->length : 0;

        if ($total === 0) {
            return $this->pass('image_alt', 'No images to check', '0 images');
        }

        $missing = $xpath->query('//img[not(@alt) or normalize-space(@alt)=""]');
        $missingCount = $missing ? $missing->length : 0;

        if ($missingCount === 0) {
            return $this->pass('image_alt', 'All images have alt text', "{$total} images, all described");
        }

        return $this->warn('image_alt', 'medium', 'Images missing alt text',
            "{$missingCount} of {$total} images have no alt text. Alt text is both an accessibility requirement and a way for images to rank.",
            "{$missingCount} of {$total} missing");
    }

    private function checkIndexable(DOMXPath $xpath): array
    {
        $robots = strtolower($this->attr($xpath, '//meta[translate(@name,"ROBOTS","robots")="robots"]/@content'));

        // The highest-severity check in the set. A noindex on a page the
        // client expects to rank makes every other finding irrelevant,
        // so it is called out loudly rather than buried as a warning.
        if (str_contains($robots, 'noindex')) {
            return $this->fail('indexable', 'high', 'Page is set to noindex',
                'This page explicitly tells search engines not to index it. Nothing else in this report matters until that is removed.', $robots);
        }

        return $this->pass('indexable', 'Page is indexable', $robots !== '' ? $robots : 'no robots meta tag (indexable by default)');
    }

    private function checkOpenGraph(DOMXPath $xpath): array
    {
        $title = $this->attr($xpath, '//meta[@property="og:title"]/@content');
        $image = $this->attr($xpath, '//meta[@property="og:image"]/@content');

        if ($title !== '' && $image !== '') {
            return $this->pass('open_graph', 'Open Graph tags present for social sharing', $title);
        }

        $missing = [];
        if ($title === '') { $missing[] = 'og:title'; }
        if ($image === '') { $missing[] = 'og:image'; }

        return $this->warn('open_graph', 'low', 'Incomplete Open Graph tags',
            'Missing ' . implode(' and ', $missing) . '. Without these, links shared on social media show no image or headline.',
            implode(', ', $missing) . ' missing');
    }

    private function checkHeadingOrder(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');

        if (! $nodes || $nodes->length === 0) {
            return $this->warn('heading_order', 'low', 'No headings found',
                'Headings give a page structure that both readers and search engines rely on.', '0 headings');
        }

        $previous = 0;
        foreach ($nodes as $node) {
            $level = (int) substr($node->nodeName, 1);
            // Skipping a level (h2 straight to h4) breaks the outline
            // for screen readers. Low severity - it is a quality signal,
            // not a ranking one.
            if ($previous > 0 && $level > $previous + 1) {
                return $this->warn('heading_order', 'low', 'Heading levels skip a level',
                    "Found an H{$level} directly after an H{$previous}. Headings should step down one level at a time.",
                    "H{$previous} to H{$level}");
            }
            $previous = $level;
        }

        return $this->pass('heading_order', 'Heading structure is in order', $nodes->length . ' headings');
    }

    private function checkWordCount(DOMXPath $xpath): array
    {
        foreach ($xpath->query('//script|//style|//noscript') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $body = $xpath->query('//body')->item(0);
        $text = $body ? trim(preg_replace('/\s+/u', ' ', $body->textContent)) : '';
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

        if ($words >= 300) {
            return $this->pass('word_count', 'Page has a reasonable amount of content', "{$words} words");
        }

        // A warning, never a failure. This engine does not run
        // JavaScript, so a client-rendered page legitimately looks empty
        // here. Scoring that as a hard fail would be confidently wrong
        // about a whole class of perfectly good sites.
        return $this->warn('word_count', 'medium', 'Thin content',
            "Only {$words} words of visible text were found. Note that this audit does not run JavaScript, so a page that builds its content in the browser will under-report here.",
            "{$words} words");
    }

    private function checkRobotsTxt(string $url): array
    {
        return $this->checkRootFile($url, '/robots.txt', 'robots_txt', 'robots.txt',
            'A robots.txt tells crawlers what they may fetch and where the sitemap is.', 'low');
    }

    private function checkSitemap(string $url): array
    {
        return $this->checkRootFile($url, '/sitemap.xml', 'sitemap', 'XML sitemap',
            'A sitemap helps search engines discover every page, especially ones not well linked internally.', 'medium');
    }

    private function checkRootFile(string $url, string $path, string $key, string $label, string $why, string $severity): array
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return $this->warn($key, 'low', "Could not check {$label}", 'The site URL could not be parsed.', null);
        }

        $target = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . $path;

        try {
            $response = Http::timeout(10)->get($target);
        } catch (\Throwable) {
            return $this->warn($key, 'low', "Could not check {$label}", 'The request timed out or failed.', $target);
        }

        return $response->successful()
            ? $this->pass($key, ucfirst($label) . ' found', $target)
            : $this->warn($key, $severity, "No {$label} found", $why, $target . ' returned HTTP ' . $response->status());
    }

    // ----------------------------------------------------------- helpers

    private function parse(string $html): DOMXPath
    {
        $doc = new DOMDocument();

        // Real-world markup is malformed constantly. Suppressing libxml
        // errors and pressing on is correct here: an audit that refused
        // to run on imperfect HTML would refuse to run on most of the
        // web, which is the exact audience for this tool.
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function text(DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)?->item(0);
        return $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent)) : '';
    }

    private function attr(DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)?->item(0);
        return $node ? trim($node->nodeValue) : '';
    }

    private function pass(string $check, string $title, ?string $value): array
    {
        return ['check' => $check, 'status' => 'pass', 'severity' => 'low', 'title' => $title, 'detail' => null, 'value' => $value];
    }

    private function warn(string $check, string $severity, string $title, string $detail, ?string $value): array
    {
        return compact('check', 'severity', 'title', 'detail', 'value') + ['status' => 'warn'];
    }

    private function fail(string $check, string $severity, string $title, string $detail, ?string $value): array
    {
        return compact('check', 'severity', 'title', 'detail', 'value') + ['status' => 'fail'];
    }

    private function score(array $findings): int
    {
        $score = 100;

        foreach ($findings as $finding) {
            if ($finding['status'] === 'pass') {
                continue;
            }

            $penalty = self::WEIGHTS[$finding['severity']] ?? 5;

            // A warning is half the cost of an outright failure. Same
            // check, lesser problem - a short title and a missing title
            // should not land in the same place.
            $score -= $finding['status'] === 'warn' ? (int) ceil($penalty / 2) : $penalty;
        }

        return max(0, min(100, $score));
    }

    private function readableError(string $message): string
    {
        return match (true) {
            str_contains($message, 'cURL error 6'), str_contains($message, 'Could not resolve host')
                => 'The domain could not be resolved. Check the URL is spelled correctly and the DNS is live.',
            str_contains($message, 'cURL error 28'), str_contains($message, 'timed out')
                => 'The site did not respond within ' . self::TIMEOUT . ' seconds.',
            str_contains($message, 'cURL error 7')
                => 'The connection was refused. The server may be down or blocking automated requests.',
            str_contains($message, 'SSL'), str_contains($message, 'certificate')
                => 'The site has an SSL certificate problem, so the page could not be fetched securely.',
            default => 'The page could not be fetched: ' . mb_substr($message, 0, 200),
        };
    }
}
