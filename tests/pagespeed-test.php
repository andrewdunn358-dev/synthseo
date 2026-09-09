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
        'performance' => ['score' => 0.86],
        'seo' => ['score' => 1],
        'accessibility' => ['score' => 0.88],
        'best-practices' => ['score' => 1],
    ],
    'audits' => [
        'largest-contentful-paint' => ['numericValue' => 3412.7],
        'total-blocking-time' => ['numericValue' => 0],
        'cumulative-layout-shift' => ['numericValue' => 0.0451],
        'render-blocking-resources' => [
            'score' => 0.2,
            'description' => 'Resources are blocking. [Learn more](https://developer.chrome.com/docs/x).',
            'displayValue' => 'Potential savings of 1,920 ms',
        ],
        'uses-long-cache-ttl' => [
            'score' => 0.45,
            'description' => 'A long cache lifetime speeds repeat visits.',
            'displayValue' => 'Est savings of 2,085 KiB',
        ],
        'uses-text-compression' => ['score' => 1, 'description' => 'All good.'],
        'modern-image-formats' => ['score' => 0.75, 'description' => 'Use WebP.', 'displayValue' => '1,529 KiB'],
        'unused-javascript' => ['score' => null, 'description' => 'Not applicable.'],
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

check('a badly failing opportunity is included', in_array('render-blocking-resources', $keys, true));
check('a passing opportunity is excluded', ! in_array('uses-text-compression', $keys, true));
check('a null-score audit is excluded', ! in_array('unused-javascript', $keys, true));
check('every finding is tagged as lighthouse', array_unique(array_column($findings, 'source')) === ['lighthouse']);

$blocking = $findings[array_search('render-blocking-resources', $keys, true)];
check('score below 0.5 is a fail', $blocking['status'] === 'fail', $blocking['status']);
check('the displayValue is carried as evidence', $blocking['value'] === 'Potential savings of 1,920 ms');

$images = $findings[array_search('modern-image-formats', $keys, true)];
check('score of 0.75 is a warn, not a fail', $images['status'] === 'warn', $images['status']);

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

echo "\n";
if ($failures) {
    echo count($failures) . " FAILURE(S):\n";
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "All PageSpeed parsing tests passed.\n";

}
