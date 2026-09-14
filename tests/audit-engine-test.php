<?php

/**
 * Audit engine tests. Run: php tests/audit-engine-test.php
 *
 * Deliberately standalone rather than a PHPUnit suite: it needs no
 * database, no Laravel boot and no vendor autoload beyond what it
 * stubs, so it runs anywhere - including on the 20i shell where
 * spinning up the full framework for a parser test is overkill.
 *
 * What it actually tests is the parsing and scoring, which is where the
 * bugs live. The HTTP fetch is not tested here; that needs a real
 * request and is verified separately against a live site.
 */

// Minimal stand-ins so the service can be loaded without Laravel.
// Only the fetch path touches Http, and these tests call the check
// methods directly rather than run().
namespace Illuminate\Support\Facades { class Http {} }

namespace {

require __DIR__ . '/../app/Services/SeoAuditService.php';

use App\Services\SeoAuditService;

$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . (! $ok && $detail ? "  [{$detail}]" : '') . "\n";
    if (! $ok) { $failures[] = $label; }
}

/** Calls a private check method with a parsed document. */
function runCheck(string $method, string $html): array
{
    $service = new SeoAuditService();
    $ref = new ReflectionClass($service);

    $parse = $ref->getMethod('parse');
    $parse->setAccessible(true);
    $xpath = $parse->invoke($service, $html);

    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invoke($service, $xpath);
}

function runScore(array $findings): int
{
    $service = new SeoAuditService();
    $ref = new ReflectionClass($service);
    $m = $ref->getMethod('score');
    $m->setAccessible(true);
    return $m->invoke($service, $findings);
}

$page = fn (string $head = '', string $body = '', string $htmlAttr = '') =>
    "<!DOCTYPE html><html{$htmlAttr}><head>{$head}</head><body>{$body}</body></html>";

echo "=== 1. TITLE ===\n";
$r = runCheck('checkTitle', $page('<title>A perfectly reasonable page title here</title>'));
check('a good title passes', $r['status'] === 'pass', $r['status']);
check('the title itself is recorded as the value', str_contains($r['value'], 'perfectly reasonable'));

$r = runCheck('checkTitle', $page(''));
check('a missing title fails at high severity', $r['status'] === 'fail' && $r['severity'] === 'high');

$r = runCheck('checkTitle', $page('<title>Home</title>'));
check('a four-character title warns as short', $r['status'] === 'warn', $r['status']);

$r = runCheck('checkTitle', $page('<title>' . str_repeat('long ', 20) . '</title>'));
check('an over-long title warns about truncation', $r['status'] === 'warn' && $r['severity'] === 'low');

echo "\n=== 2. META DESCRIPTION ===\n";
$good = '<meta name="description" content="' . str_repeat('word ', 20) . '">';
$r = runCheck('checkMetaDescription', $page($good));
check('a sensible description passes', $r['status'] === 'pass', $r['status']);

$r = runCheck('checkMetaDescription', $page(''));
check('a missing description fails', $r['status'] === 'fail');

// Case-insensitivity matters: plenty of real sites ship name="Description".
$r = runCheck('checkMetaDescription', $page('<meta name="Description" content="' . str_repeat('word ', 20) . '">'));
check('name="Description" is matched case-insensitively', $r['status'] === 'pass', $r['status']);

echo "\n=== 3. H1 ===\n";
$r = runCheck('checkH1', $page('', '<h1>The one heading</h1>'));
check('exactly one H1 passes', $r['status'] === 'pass');
check('the heading text is recorded', $r['value'] === 'The one heading', $r['value'] ?? 'null');

$r = runCheck('checkH1', $page('', '<p>no headings</p>'));
check('no H1 fails', $r['status'] === 'fail');

$r = runCheck('checkH1', $page('', '<h1>One</h1><h1>Two</h1>'));
check('two H1s warn rather than fail', $r['status'] === 'warn', $r['status']);

echo "\n=== 4. INDEXABLE — THE ONE THAT MATTERS MOST ===\n";
$r = runCheck('checkIndexable', $page('<meta name="robots" content="noindex, nofollow">'));
check('noindex is caught', $r['status'] === 'fail' && $r['severity'] === 'high');

$r = runCheck('checkIndexable', $page('<meta name="ROBOTS" content="NOINDEX">'));
check('noindex is caught in upper case too', $r['status'] === 'fail', $r['status']);

$r = runCheck('checkIndexable', $page(''));
check('no robots tag means indexable, not unknown', $r['status'] === 'pass');

$r = runCheck('checkIndexable', $page('<meta name="robots" content="index, follow">'));
check('an explicit index directive passes', $r['status'] === 'pass');

echo "\n=== 5. IMAGE ALT TEXT ===\n";
$r = runCheck('checkImageAlts', $page('', '<img src="a.jpg" alt="A cat"><img src="b.jpg" alt="A dog">'));
check('all images described passes', $r['status'] === 'pass');

$r = runCheck('checkImageAlts', $page('', '<img src="a.jpg" alt="A cat"><img src="b.jpg">'));
check('a missing alt warns', $r['status'] === 'warn');
check('the count is reported honestly', $r['value'] === '1 of 2 missing', $r['value'] ?? 'null');

// An empty alt is not the same as a described image.
$r = runCheck('checkImageAlts', $page('', '<img src="a.jpg" alt="   ">'));
check('a whitespace-only alt counts as missing', $r['status'] === 'warn', $r['status']);

$r = runCheck('checkImageAlts', $page('', '<p>no images</p>'));
check('a page with no images passes rather than warning', $r['status'] === 'pass');

echo "\n=== 6. VIEWPORT, LANG, CANONICAL ===\n";
$r = runCheck('checkViewport', $page('<meta name="viewport" content="width=device-width, initial-scale=1">'));
check('viewport present passes', $r['status'] === 'pass');
$r = runCheck('checkViewport', $page(''));
check('missing viewport fails at high severity', $r['status'] === 'fail' && $r['severity'] === 'high');

$r = runCheck('checkLang', $page('', '', ' lang="en-GB"'));
check('lang attribute is read', $r['status'] === 'pass' && $r['value'] === 'en-GB', $r['value'] ?? 'null');
$r = runCheck('checkLang', $page());
check('missing lang warns at low severity', $r['status'] === 'warn' && $r['severity'] === 'low');

$r = runCheck('checkCanonical', $page('<link rel="canonical" href="https://example.co.uk/">'));
check('canonical present passes', $r['status'] === 'pass');
$r = runCheck('checkCanonical', $page(''));
check('missing canonical warns', $r['status'] === 'warn');

echo "\n=== 7. HEADING ORDER ===\n";
$r = runCheck('checkHeadingOrder', $page('', '<h1>A</h1><h2>B</h2><h3>C</h3><h2>D</h2>'));
check('a well-ordered outline passes', $r['status'] === 'pass', $r['status']);

$r = runCheck('checkHeadingOrder', $page('', '<h1>A</h1><h4>B</h4>'));
check('skipping H2 and H3 warns', $r['status'] === 'warn', $r['status']);
check('the skip is described', str_contains($r['value'], 'H1 to H4'), $r['value'] ?? 'null');

echo "\n=== 8. WORD COUNT — SCRIPTS MUST NOT COUNT ===\n";
// A page whose only "text" is a huge inline script is thin content,
// however many characters the DOM holds. Getting this wrong would make
// every JS-heavy page look content-rich.
$script = '<script>' . str_repeat('var alpha = beta + gamma; ', 200) . '</script>';
$r = runCheck('checkWordCount', $page('', $script . '<p>Only a few real words here.</p>'));
check('script contents are excluded from the word count', $r['status'] === 'warn', $r['status']);
check('the count reflects visible text only', (int) filter_var($r['value'], FILTER_SANITIZE_NUMBER_INT) < 20, $r['value'] ?? 'null');

$r = runCheck('checkWordCount', $page('', '<p>' . str_repeat('word ', 400) . '</p>'));
check('a substantial page passes', $r['status'] === 'pass', $r['status']);

$r = runCheck('checkWordCount', $page('', '<p>short</p>'));
check('thin content warns, never fails (no JS rendering here)', $r['status'] === 'warn' && $r['status'] !== 'fail');

echo "\n=== 9. MALFORMED HTML MUST NOT CRASH ===\n";
// The whole audience for this tool is sites with imperfect markup.
$broken = '<html><head><title>Unclosed<body><p>Mismatched</div><img src=x alt=y>';
$r = runCheck('checkTitle', $broken);
check('an unclosed title tag still parses', is_array($r) && isset($r['status']), 'threw or returned nothing');
$r = runCheck('checkImageAlts', $broken);
check('unquoted attributes still parse', $r['status'] === 'pass', $r['status']);

echo "\n=== 10. SCORING ===\n";
$allPass = array_fill(0, 10, ['check' => 'x', 'status' => 'pass', 'severity' => 'low']);
check('a clean sheet scores 100', runScore($allPass) === 100, (string) runScore($allPass));

$oneHighFail = [['check' => 'x', 'status' => 'fail', 'severity' => 'high']];
check('one high-severity failure costs 15', runScore($oneHighFail) === 85, (string) runScore($oneHighFail));

$oneHighWarn = [['check' => 'x', 'status' => 'warn', 'severity' => 'high']];
check('the same check as a warning costs half', runScore($oneHighWarn) === 92, (string) runScore($oneHighWarn));

$disaster = array_fill(0, 20, ['check' => 'x', 'status' => 'fail', 'severity' => 'high']);
check('the score floors at 0 rather than going negative', runScore($disaster) === 0, (string) runScore($disaster));

echo "\n=== 11. URL RESOLUTION (broken-link checking) ===\n";
// Pure logic, no HTTP - checkBrokenLinks itself is untested here for
// the same reason checkRobotsTxt/checkSitemap are: the entire point is
// a live request per link, which has nothing left to verify once the
// HTTP client is stubbed out. This is the part of it that can be
// tested, and it is where a resolver actually gets things wrong.
function runResolve(?string $base, string $href): ?string
{
    $service = new SeoAuditService();
    $ref = new ReflectionClass($service);
    $m = $ref->getMethod('resolveUrl');
    $m->setAccessible(true);
    return $m->invoke($service, $base, $href);
}

check('an absolute https href is returned unchanged',
    runResolve('https://example.com/blog/post', 'https://other.com/x') === 'https://other.com/x');

check('a protocol-relative href takes the base scheme',
    runResolve('https://example.com/blog/post', '//cdn.example.com/x.js') === 'https://cdn.example.com/x.js');

check('a root-relative href resolves against the origin',
    runResolve('https://example.com/blog/post', '/contact') === 'https://example.com/contact');

check('a bare-relative href resolves against the current directory',
    runResolve('https://example.com/blog/post', 'next') === 'https://example.com/blog/next');

check('a bare-relative href resolves correctly with a trailing slash on base',
    runResolve('https://example.com/blog/', 'next') === 'https://example.com/blog/next');

check('a bare-relative href resolves against the root when base has no path',
    runResolve('https://example.com', 'contact') === 'https://example.com/contact');

check('a port on the base is preserved',
    runResolve('https://example.com:8080/blog/post', 'next') === 'https://example.com:8080/blog/next');

check('an unparseable base returns null rather than a wrong guess',
    runResolve('not a url', '/contact') === null);

echo "\n=== 12. CMS DETECTION ===\n";
function runDetectCms(string $html): ?string
{
    $service = new SeoAuditService();
    $ref = new ReflectionClass($service);
    $m = $ref->getMethod('detectCms');
    $m->setAccessible(true);
    return $m->invoke($service, $html);
}

check('wp-content path is detected as WordPress',
    runDetectCms('<html><body><img src="/wp-content/uploads/x.jpg"></body></html>') === 'WordPress');

check('the WordPress generator meta tag is detected',
    runDetectCms('<meta name="generator" content="WordPress 6.4">') === 'WordPress');

check('a Shopify CDN reference is detected as Shopify',
    runDetectCms('<script src="https://cdn.shopify.com/s/x.js"></script>') === 'Shopify');

check('a Wix static asset host is detected as Wix',
    runDetectCms('<img src="https://static.wixstatic.com/media/x.jpg">') === 'Wix');

check('a Squarespace context script is detected as Squarespace',
    runDetectCms('<script>Static.SQUARESPACE_CONTEXT = {};</script>') === 'Squarespace');

check('a data-wf-site attribute is detected as Webflow',
    runDetectCms('<html data-wf-site="abc123">') === 'Webflow');

check('the Joomla generator meta tag is detected',
    runDetectCms('<meta name="generator" content="Joomla! - Open Source Content Management">') === 'Joomla');

check('a Drupal.settings reference is detected as Drupal',
    runDetectCms('<script>var Drupal = Drupal || {}; Drupal.settings = {};</script>') === 'Drupal');

check('plain HTML with no platform footprint returns null, not a wrong guess',
    runDetectCms('<html><body><h1>Hello</h1></body></html>') === null);

echo "\n";
if ($failures) {
    echo count($failures) . " FAILURE(S):\n";
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "All audit engine tests passed.\n";

}
