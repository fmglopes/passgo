<?php
/**
 * PASS GO test runner.
 *
 *   php tests/run.php                 run everything (unit + integration + flows)
 *   php tests/run.php --filter=book   only tests whose section or name contains "book"
 *   php tests/run.php --no-flow       skip the HTTP flow tests (no web server needed)
 *   php tests/run.php --unit          only the pure-logic unit tests (no database needed)
 *
 * The tests use a SEPARATE PostgreSQL database that is wiped and re-created every run.
 * Point them at it with environment variables, for example:
 *   PASSGO_DB_NAME=passgo_test PASSGO_DB_HOST=localhost PASSGO_DB_USER=passgo PASSGO_DB_PASSWORD=... php tests/run.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/lib/Test.php';
require __DIR__ . '/lib/Db.php';
require __DIR__ . '/lib/Http.php';

$filter = '';
$unitOnly = false;
$withFlows = true;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--filter=') === 0) {
        $filter = substr($arg, 9);
    } elseif ($arg === '--unit') {
        $unitOnly = true;
    } elseif ($arg === '--no-flow') {
        $withFlows = false;
    }
}

foreach (glob(__DIR__ . '/unit/*_test.php') as $file) {
    require $file;
}

$server = null;
if (!$unitOnly) {
    reset_database();
    foreach (glob(__DIR__ . '/integration/*_test.php') as $file) {
        require $file;
    }
    if ($withFlows) {
        [$server, $GLOBALS['BASE']] = start_test_server();
        require __DIR__ . '/flow/flows_test.php';
    }
}

$failures = run_tests($filter);
stop_test_server($server);
exit($failures > 0 ? 1 : 0);
