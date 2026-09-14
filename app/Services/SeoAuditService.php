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

    /** Internal links sampled for the broken-link check, not every
     *  link on the page. A large site can have hundreds; checking all
     *  of them would turn one audit's single finding into a full-site
     *  crawl, which the class doc already rules out. Capped low enough
     *  that worst case (all timeouts) still leaves room inside the
     *  job's overall timeout alongside PageSpeed's own 90s. */
    private const MAX_LINKS_CHECKED = 8;

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
                'cms' => null,
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
            // homepage. Stop here and say so. CMS detection still runs
            // on the raw HTML though - even a themed error page often
            // still carries WordPress/Shopify/etc footprints, and that
            // is genuinely useful context to have even from a failed
            // fetch.
            return [
                'score' => $this->score($findings),
                'http_status' => $response->status(),
                'response_ms' => $responseMs,
                'error' => 'The page returned HTTP ' . $response->status() . ', so on-page checks were skipped.',
                'findings' => $findings,
                'cms' => $this->detectCms($html),
            ];
        }

        $xpath = $this->parse($html);

        $findings[] = $this->checkTitle($xpath);
        $findings[] = $this->checkMetaDescription($xpath);
        $findings[] = $this->checkH1($xpath);
        $findings[] = $this->checkCanonical($xpath);
        $findings[] = $this->checkViewport($xpath);
        $findings[] = $this->checkLang($xpath);
        $findings[] = $this->checkImageAlts($xpath, $url);
        $findings[] = $this->checkIndexable($xpath);
        $findings[] = $this->checkOpenGraph($xpath);
        $findings[] = $this->checkHeadingOrder($xpath);
        $findings[] = $this->checkWordCount($xpath);
        $findings[] = $this->checkRobotsTxt($url);
        $findings[] = $this->checkSitemap($url);
        $findings[] = $this->checkBrokenLinks($xpath, $url);

        return [
            'score' => $this->score($findings),
            'http_status' => $response->status(),
            'response_ms' => $responseMs,
            'error' => null,
            'findings' => $findings,
            'cms' => $this->detectCms($html),
        ];
    }

    /**
     * Sniffs common CMS/platform footprints from the raw HTML.
     * Best-effort and deliberately narrow: returns null rather than a
     * wrong guess when nothing matches, because a confidently wrong
     * platform label would make AI-generated advice confidently wrong
     * too ("open your WordPress admin" to someone who has no WordPress
     * admin) - worse than staying generic.
     */
    private function detectCms(string $html): ?string
    {
        return match (true) {
            str_contains($html, 'wp-content') || str_contains($html, 'wp-includes')
                || preg_match('/name="generator"\s+content="WordPress/i', $html) === 1 => 'WordPress',
            str_contains($html, 'cdn.shopify.com') || str_contains($html, 'Shopify.theme') => 'Shopify',
            str_contains($html, 'static.wixstatic.com') => 'Wix',
            str_contains($html, 'squarespace.com') || str_contains($html, 'Static.SQUARESPACE_CONTEXT') => 'Squarespace',
            preg_match('/data-wf-(site|page)/i', $html) === 1 => 'Webflow',
            preg_match('/name="generator"\s+content="Joomla/i', $html) === 1 => 'Joomla',
            str_contains($html, 'Drupal.settings') || str_contains($html, '/sites/default/files/') => 'Drupal',
            default => null,
        };
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
        //
        // Measured by an identified bot (see the User-Agent this
        // service sends), not a real browser - and a real test against
        // a live site proved the two can differ enormously (0.7s for a
        // browser UA, 4.3s for this exact bot UA, same URL, seconds
        // apart). A slow figure here can mean a genuinely slow server,
        // but it can just as easily mean security software or a
        // firewall specifically slowing down or challenging automated
        // requests - which would very plausibly do the same thing to
        // Googlebot, a real and separate SEO problem worth naming
        // rather than silently folding into "your server is slow".
        $caveat = ' This was measured by an identified bot, not a real visitor\'s browser - if this number seems '
            . 'surprisingly high, security software treating automated requests differently (which can also slow '
            . 'down Google\'s own crawler) is a common cause, not just server capacity.';

        return $ms <= 3000
            ? $this->warn('response_time', 'medium', 'Server response is slow',
                "The server took {$ms} ms to respond. This is server time only, not full page load.{$caveat}", "{$ms} ms")
            : $this->fail('response_time', 'medium', 'Server response is very slow',
                "The server took {$ms} ms to respond. This is server time only, not full page load.{$caveat}", "{$ms} ms");
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

    private function checkImageAlts(DOMXPath $xpath, string $baseUrl): array
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

        // The specific images missing alt text, not just a count - real
        // thumbnails on the audit page so "which ones" doesn't require
        // opening dev tools to find out. Same resolveUrl() already used
        // for broken-link checking, and the same 4-image cap
        // PageSpeedService's own image findings use.
        $images = [];

        foreach ($missing as $img) {
            $src = trim($img->getAttribute('src'));

            if ($src === '') {
                continue;
            }

            $resolved = $this->resolveUrl($baseUrl, $src);

            if ($resolved) {
                $images[] = ['url' => $resolved, 'wasted_bytes' => null, 'total_bytes' => null];
            }

            if (count($images) >= 4) {
                break;
            }
        }

        return $this->warn('image_alt', 'medium', 'Images missing alt text',
            "{$missingCount} of {$total} images have no alt text. Alt text is both an accessibility requirement and a way for images to rank.",
            "{$missingCount} of {$total} missing") + ['images' => $images ?: null];
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

    /**
     * Samples internal links from the page and checks each resolves.
     * Not unit-tested below for the same reason checkRobotsTxt and
     * checkSitemap aren't - the entire point is a live HTTP call per
     * link, which has nothing left to test once you stub the HTTP
     * client out. Verified against a live site instead.
     */
    private function checkBrokenLinks(DOMXPath $xpath, string $baseUrl): array
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! $host) {
            return $this->warn('broken_links', 'low', 'Could not check internal links', 'The site URL could not be parsed.', null);
        }

        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $a) {
            $href = trim($a->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#')
                || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }

            $resolved = $this->resolveUrl($baseUrl, $href);

            // Same-host only. An audit reporting someone else's broken
            // link as if it were the client's problem would be wrong,
            // not just unhelpful.
            if ($resolved && parse_url($resolved, PHP_URL_HOST) === $host) {
                $links[$resolved] = true;
            }
        }

        $links = array_keys($links);

        if ($links === []) {
            return $this->pass('broken_links', 'No internal links found to check', '0 links');
        }

        $sample = array_slice($links, 0, self::MAX_LINKS_CHECKED);
        $broken = [];

        foreach ($sample as $link) {
            $status = $this->fetchStatus($link);

            if ($status === null || $status >= 400) {
                $broken[] = $link . ($status ? " ({$status})" : ' (no response)');
            }
        }

        $checkedNote = count($links) > count($sample)
            ? count($sample) . ' of ' . count($links) . ' internal links sampled'
            : count($sample) . ' internal links checked';

        if ($broken === []) {
            return $this->pass('broken_links', 'No broken internal links found', $checkedNote);
        }

        return $this->warn('broken_links', 'medium', 'Broken internal links found',
            count($broken) . ' of ' . count($sample) . ' checked links returned an error or did not respond.',
            implode(', ', array_slice($broken, 0, 5)));
    }

    /** HEAD first since it costs less than downloading the page; a
     *  GET fallback for the servers (not rare) that reject HEAD
     *  outright rather than actually being broken. */
    private function fetchStatus(string $url): ?int
    {
        try {
            $status = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'SynthSEO-Audit/1.0 (+https://synthseo.co.uk)'])
                ->head($url)
                ->status();

            if (in_array($status, [405, 501], true)) {
                $status = Http::timeout(6)
                    ->withHeaders(['User-Agent' => 'SynthSEO-Audit/1.0 (+https://synthseo.co.uk)'])
                    ->get($url)
                    ->status();
            }

            return $status;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolves an href found on $base into an absolute URL. Handles
     * the cases that actually occur in real markup - protocol-relative,
     * root-relative, and path-relative - without pulling in a URL
     * library for what real pages need in practice.
     */
    private function resolveUrl(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $baseParts = parse_url($base);

        if (! isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $origin = $baseParts['scheme'] . '://' . $baseParts['host']
            . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '');

        if (str_starts_with($href, '//')) {
            return $baseParts['scheme'] . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        $basePath = $baseParts['path'] ?? '/';
        $dir = strrpos($basePath, '/') !== false ? substr($basePath, 0, strrpos($basePath, '/') + 1) : '/';

        return $origin . $dir . $href;
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
