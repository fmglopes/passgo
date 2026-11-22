<?php
// A tiny test framework (no Composer or PHPUnit needed, so it runs anywhere PHP runs).

class TestFailure extends Exception
{
}

$GLOBALS['__tests'] = [];
$GLOBALS['__section'] = '';

function section(string $title): void
{
    $GLOBALS['__section'] = $title;
}

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = ['section' => $GLOBALS['__section'], 'name' => $name, 'fn' => $fn];
}

function fail(string $message): void
{
    throw new TestFailure($message);
}

function export($v): string
{
    return var_export($v, true);
}

function assertTrue($cond, string $msg = ''): void
{
    if ($cond !== true) {
        fail($msg !== '' ? $msg : 'Expected true, got ' . export($cond));
    }
}

function assertFalse($cond, string $msg = ''): void
{
    if ($cond !== false) {
        fail($msg !== '' ? $msg : 'Expected false, got ' . export($cond));
    }
}

function assertSame($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        fail(($msg !== '' ? $msg . ': ' : '') . 'Expected ' . export($expected) . ', got ' . export($actual));
    }
}

function assertContains(string $needle, string $haystack, string $msg = ''): void
{
    if (strpos($haystack, $needle) === false) {
        fail(($msg !== '' ? $msg . ': ' : '') . 'Expected to find ' . export($needle) . ' in output (' . strlen($haystack) . ' bytes)');
    }
}

function assertNotContains(string $needle, string $haystack, string $msg = ''): void
{
    if (strpos($haystack, $needle) !== false) {
        fail(($msg !== '' ? $msg . ': ' : '') . 'Did not expect to find ' . export($needle));
    }
}

function assertMatches(string $pattern, string $subject, string $msg = ''): void
{
    if (!preg_match($pattern, $subject)) {
        fail(($msg !== '' ? $msg . ': ' : '') . 'Expected ' . export($subject) . ' to match ' . $pattern);
    }
}

/** Assert that $fn throws $class (optionally with $messageContains in its message). */
function assertThrows(string $class, callable $fn, ?string $messageContains = null): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!($e instanceof $class)) {
            fail('Expected ' . $class . ', got ' . get_class($e) . ': ' . $e->getMessage());
        }
        if ($messageContains !== null && strpos($e->getMessage(), $messageContains) === false) {
            fail('Expected message containing ' . export($messageContains) . ', got ' . export($e->getMessage()));
        }
        return $e;
    }
    fail('Expected ' . $class . ' to be thrown, but nothing was thrown');
}

/** Run all registered tests. Returns the number of failures. */
function run_tests(string $filter = ''): int
{
    $passed = 0;
    $failed = 0;
    $lastSection = null;
    $failures = [];
    foreach ($GLOBALS['__tests'] as $t) {
        if ($filter !== '' && stripos($t['section'] . ' ' . $t['name'], $filter) === false) {
            continue;
        }
        if ($t['section'] !== $lastSection) {
            echo "\n== " . $t['section'] . " ==\n";
            $lastSection = $t['section'];
        }
        try {
            $t['fn']();
            echo "  PASS  " . $t['name'] . "\n";
            $passed++;
        } catch (Throwable $e) {
            echo "  FAIL  " . $t['name'] . "\n        " . str_replace("\n", "\n        ", $e->getMessage()) . "\n";
            $failed++;
            $failures[] = $t['section'] . ' / ' . $t['name'];
        }
    }
    echo "\n----------------------------------------\n";
    echo "Passed: $passed   Failed: $failed   Total: " . ($passed + $failed) . "\n";
    if ($failures) {
        echo "Failures:\n";
        foreach ($failures as $f) {
            echo "  - $f\n";
        }
    }
    return $failed;
}
