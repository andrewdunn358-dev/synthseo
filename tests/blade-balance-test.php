<?php

/**
 * Checks Blade control structures are balanced across every view.
 *
 * Not a substitute for compiling the templates - it cannot catch a bad
 * PHP expression inside {{ }}. It catches the failure mode that bites
 * when editing views by hand: a directive opened and never closed,
 * which Blade reports as an "unexpected end of file" pointing at the
 * wrong file entirely.
 *
 * Two subtleties that a naive version of this gets wrong, and which
 * made the first attempt report every view as broken:
 *
 *   @section('title', 'x')  - the two-argument form is self-closing and
 *                             must NOT be counted as an opener.
 *   @empty                  - bare, it is a @forelse separator. With
 *                             arguments, @empty($x), it is a block
 *                             opener needing @endempty. Same word,
 *                             opposite meanings.
 *
 * Run: php tests/blade-balance-test.php
 */

$pairs = [
    'if' => 'endif', 'foreach' => 'endforeach', 'forelse' => 'endforelse',
    'for' => 'endfor', 'while' => 'endwhile', 'section' => 'endsection',
    'push' => 'endpush', 'once' => 'endonce', 'error' => 'enderror',
    'isset' => 'endisset', 'empty' => 'endempty', 'auth' => 'endauth',
    'guest' => 'endguest', 'unless' => 'endunless', 'verbatim' => 'endverbatim',
];
$closers = array_flip($pairs);

/** Reads the balanced parenthesised argument list starting at $from. */
function directiveArgs(string $src, int $from): ?string
{
    if (($src[$from] ?? '') !== '(') { return null; }
    $depth = 0;
    for ($i = $from, $len = strlen($src); $i < $len; $i++) {
        if ($src[$i] === '(') { $depth++; }
        elseif ($src[$i] === ')') {
            $depth--;
            if ($depth === 0) { return substr($src, $from + 1, $i - $from - 1); }
        }
    }
    return null;
}

/** True if the argument list has a comma at the top nesting level. */
function hasTopLevelComma(string $args): bool
{
    $depth = 0; $quote = null;
    for ($i = 0, $len = strlen($args); $i < $len; $i++) {
        $c = $args[$i];
        if ($quote) { if ($c === $quote && $args[$i - 1] !== '\\') { $quote = null; } continue; }
        if ($c === '"' || $c === "'") { $quote = $c; }
        elseif ($c === '(' || $c === '[') { $depth++; }
        elseif ($c === ')' || $c === ']') { $depth--; }
        elseif ($c === ',' && $depth === 0) { return true; }
    }
    return false;
}

$failures = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../resources/views'));
$paths = [];
foreach ($files as $file) {
    if ($file->getExtension() === 'php') { $paths[] = $file->getPathname(); }
}
sort($paths);

foreach ($paths as $path) {
    $name = 'resources/views/' . substr($path, strpos($path, 'views/') + 6);
    $src = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($path));

    preg_match_all('/@(\w+)/', $src, $m, PREG_OFFSET_CAPTURE);

    $stack = [];
    $ok = true;

    foreach ($m[1] as $i => [$directive, $offset]) {
        $after = $offset + strlen($directive);
        while (($src[$after] ?? '') === ' ') { $after++; }
        $args = directiveArgs($src, $after);

        // Bare @empty inside a forelse is a separator, not a block.
        if ($directive === 'empty' && $args === null) { continue; }

        // @section('x', 'y') renders inline and closes itself.
        if ($directive === 'section' && $args !== null && hasTopLevelComma($args)) { continue; }

        if (isset($pairs[$directive])) { $stack[] = $directive; continue; }

        if (isset($closers[$directive])) {
            $expected = $closers[$directive];
            $last = array_pop($stack);
            if ($last !== $expected) {
                $line = substr_count(substr($src, 0, $offset), "\n") + 1;
                echo "  FAIL  {$name}:{$line} — @{$directive} closes @" . ($last ?? 'nothing') . ", expected @{$expected}\n";
                $failures[] = $name;
                $ok = false;
            }
        }
    }

    if ($stack) {
        echo "  FAIL  {$name} — unclosed @" . implode(', @', $stack) . "\n";
        $failures[] = $name;
        $ok = false;
    }

    if ($ok) { echo "  PASS  {$name}\n"; }
}

echo "\n";
if ($failures) {
    echo count(array_unique($failures)) . " file(s) with unbalanced directives.\n";
    exit(1);
}
echo "All views balanced.\n";
