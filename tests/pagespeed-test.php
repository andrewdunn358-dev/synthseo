<?php

/**
 * PageSpeed response parsing tests. Run: php tests/pagespeed-test.php
 *
 * Tests the parsing, not the API call. Google's response shape is the
 * thing that will silently break this - a renamed audit key or a
 * category that comes back null - and that can be exercised against a
 * fixture without a network round trip or burning quota.
 *
 * The fixture below is trimmed from a real PSI v5 response.
 */

namespace Illuminate\Support\Facades {
    class Http {}
    class Log { public static function warning(...$a) {} }
}

namespace {

function config(string $key, $default = null) { return null; }

require __DIR__ . '/../app/Services/PageSpeedService.php';

use App\Services\PageSpeedService;

$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . (! $ok && $detail ? "  [{$detail}]" : '') . "\n";
    if (! $ok) { $failures[] = $label; }
}

function call(string $method, array $arg)
{
    $service = new PageSpeedService('test-key');
    $ref = new ReflectionClass($service);
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invoke($service, $arg);
}

$lh = [
    'finalUrl' => 'https://torque.synphony.co.uk/marketing.html',
    'requestedUrl' => 'https://torque.synphony.co.uk/',
    'categories' => [
        'performance' => [
            'score' => 0.86,
            // Lighthouse 13 renamed these; the extractor must not care.
            'auditRefs' => [
                ['id' => 'render-blocking-insight'],
                ['id' => 'cache-insight'],
                ['id' => 'image-delivery-insight'],
                ['id' => 'modern-http-insight'],
                ['id' => 'largest-contentful-paint'],
                ['id' => 'network-dependency-tree-insight'],
                ['id' => 'third-parties-insight'],
            ],
        ],
        'seo' => ['score' => 1],
        'accessibility' => ['score' => 0.88],
        'best-practices' => ['score' => 1],
    ],
    'audits' => [
        'largest-contentful-paint' => ['numericValue' => 3412.7],
        'total-blocking-time' => ['numericValue' => 0],
        'cumulative-layout-shift' => ['numericValue' => 0.0451],
        'render-blocking-insight' => [
            'score' => 0.2,
            'title' => 'Render-blocking requests',
            'description' => 'Resources are blocking. [Learn more](https://developer.chrome.com/docs/x).',
            'displayValue' => 'Potential savings of 1,920 ms',
        ],
        'cache-insight' => [
            'score' => 0.45,
            'title' => 'Use efficient cache lifetimes',
            'description' => 'A long cache lifetime speeds repeat visits.',
            'displayValue' => 'Est savings of 2,085 KiB',
        ],
        'modern-http-insight' => ['score' => 1, 'title' => 'Modern HTTP', 'description' => 'All good.'],
        'image-delivery-insight' => [
            'score' => 0.75, 'title' => 'Improve image delivery',
            'description' => 'Use WebP.', 'displayValue' => '1,529 KiB',
        ],
        'network-dependency-tree-insight' => [
            'score' => null, 'scoreDisplayMode' => 'informative',
            'title' => 'Network dependency tree', 'description' => 'Informational only.',
        ],
        'third-parties-insight' => [
            'score' => null, 'scoreDisplayMode' => 'notApplicable',
            'title' => 'Third parties', 'description' => 'Nothing to report.',
        ],
    ],
];

echo "=== 1. SCORES ARE 0-100, NOT 0-1 ===\n";
$scores = call('scores', $lh);
check('performance 0.86 becomes 86', $scores['performance'] === 86, var_export($scores['performance'], true));
check('a perfect 1 becomes 100', $scores['seo'] === 100, var_export($scores['seo'], true));
check('accessibility rounds correctly', $scores['accessibility'] === 88, var_export($scores['accessibility'], true));
check('hyphenated best-practices key is read', $scores['best-practices'] === 100, var_export($scores['best-practices'], true));

echo "\n=== 2. A MISSING CATEGORY IS NULL, NEVER ZERO ===\n";
// This is the one that would actively mislead a client: reporting 0
// for a category Lighthouse could not run says their site is broken.
$partial = $lh;
$partial['categories']['performance']['score'] = null;
$scores = call('scores', $partial);
check('a null score stays null', $scores['performance'] === null, var_export($scores['performance'], true));

unset($partial['categories']['accessibility']);
$scores = call('scores', $partial);
check('an absent category stays null', $scores['accessibility'] === null, var_export($scores['accessibility'], true));

$scores = call('scores', ['categories' => []]);
check('an empty response gives all nulls, no crash', $scores['seo'] === null && count($scores) === 4);

echo "\n=== 3. CORE WEB VITALS ===\n";
$metrics = call('metrics', $lh);
check('LCP rounds to whole milliseconds', $metrics['lcp_ms'] === 3413, var_export($metrics['lcp_ms'], true));
check('a genuine zero TBT is kept, not treated as missing', $metrics['tbt_ms'] === 0, var_export($metrics['tbt_ms'], true));
check('CLS keeps three decimals', $metrics['cls'] === 0.045, var_export($metrics['cls'], true));

$metrics = call('metrics', ['audits' => []]);
check('missing metrics are null, not zero', $metrics['lcp_ms'] === null && $metrics['cls'] === null);

echo "\n=== 4. FINDINGS ===\n";
$findings = call('findings', $lh);
$keys = array_column($findings, 'check');

// The bug that shipped: keys were hardcoded from Lighthouse 12
// ('render-blocking-resources'), Lighthouse 13 renamed them, and the
// list came back empty next to a performance score of 69 - reading as
// "no problems" rather than "extractor broken". These keys are all the
// new names, and nothing in the service knows any of them.
check('a failing insight is included under its new key', in_array('render-blocking-insight', $keys, true), implode(',', $keys));
check('a passing insight is excluded', ! in_array('modern-http-insight', $keys, true));
check('an informative audit is excluded', ! in_array('network-dependency-tree-insight', $keys, true));
check('a notApplicable audit is excluded', ! in_array('third-parties-insight', $keys, true));
check('a metric is not repeated as an issue', ! in_array('largest-contentful-paint', $keys, true));
check('every finding is tagged as lighthouse', array_unique(array_column($findings, 'source')) === ['lighthouse']);

check("Lighthouse's own title is used, not one of ours",
    $findings[0]['title'] === 'Render-blocking requests', $findings[0]['title'] ?? 'null');
check('worst-scoring finding comes first', $findings[0]['check'] === 'render-blocking-insight', $findings[0]['check']);

$blocking = $findings[array_search('render-blocking-insight', $keys, true)];
check('score below 0.5 is a fail', $blocking['status'] === 'fail', $blocking['status']);
check('the displayValue is carried as evidence', $blocking['value'] === 'Potential savings of 1,920 ms');

$images = $findings[array_search('image-delivery-insight', $keys, true)];
check('score of 0.75 is a warn, not a fail', $images['status'] === 'warn', $images['status']);

check('an audit missing from auditRefs is never invented', count($findings) === 3, (string) count($findings));

echo "\n=== 5. MARKDOWN LINKS ARE STRIPPED ===\n";
// Lighthouse descriptions are markdown. Left raw, a client-facing
// report reads as URL soup.
check('link text is kept', str_contains($blocking['detail'], 'Learn more'), $blocking['detail']);
check('the URL is removed', ! str_contains($blocking['detail'], 'developer.chrome.com'), $blocking['detail']);
check('no bracket syntax survives', ! str_contains($blocking['detail'], ']('), $blocking['detail']);

echo "\n=== 6. NOTHING CRASHES ON A MALFORMED RESPONSE ===\n";
// PSI returns partial documents under load. None of these should throw.
foreach ([[], ['audits' => null], ['categories' => null], ['audits' => ['render-blocking-resources' => 'nonsense']]] as $i => $bad) {
    try {
        call('scores', is_array($bad) ? $bad : []);
        call('metrics', is_array($bad) ? $bad : []);
        call('findings', is_array($bad) ? $bad : []);
        check("malformed response #{$i} handled", true);
    } catch (\Throwable $e) {
        check("malformed response #{$i} handled", false, $e->getMessage());
    }
}

echo "\n=== 7. THE QUERY STRING REPEATS `category` ===\n";
// The bug that shipped: an array of categories serialises as
// category[0]=..., which Google ignores, silently returning
// performance only. Asserting the built URL directly is the only way
// to catch that without a live call.
$service = new PageSpeedService('KEY123');
$ref = new ReflectionClass($service);
$prop = $ref->getProperty('apiKey');
$prop->setAccessible(true);

$built = null;
// Rebuild the query the same way run() does, from the source, so the
// test breaks if that construction changes shape.
$src = file_get_contents(__DIR__ . '/../app/Services/PageSpeedService.php');
check('categories are appended one per parameter',
    str_contains($src, "\$params[] = 'category=' . rawurlencode(\$category);"),
    'the repeated-parameter construction is gone');
check('categories are NOT passed as an array to the HTTP client',
    ! preg_match("/'category'\s*=>\s*\[/", $src),
    'an array would serialise as category[0]= and be ignored');
check('all four categories are requested',
    substr_count($src, "'performance', 'seo', 'accessibility', 'best-practices'") >= 1);
// Comments are stripped first: the file legitimately NAMES the old
// keys while explaining why they must not be used, and an assertion
// that cannot tell code from prose fails on its own documentation.
$code = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $src);
check('no Lighthouse audit keys are hardcoded in code',
    ! str_contains($code, 'render-blocking-resources') && ! str_contains($code, 'uses-long-cache-ttl'),
    'a hardcoded key list breaks silently when Google renames things');
check('findings are driven by the response auditRefs',
    str_contains($code, "auditRefs"),
    'the extractor must read the keys from the response, not know them');

echo "\n";
if ($failures) {
    echo count($failures) . " FAILURE(S):\n";
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "All PageSpeed parsing tests passed.\n";

}
