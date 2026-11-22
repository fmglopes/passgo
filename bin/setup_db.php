<?php
/**
 * Prepare the database. Safe to run on every deploy / container start.
 *
 *   - Empty database  -> create the tables (schema.sql) and load the demo data (seed.sql).
 *   - Existing tables -> change nothing except adding future time slots (topup_slots.sql),
 *                        so real bookings made during the demo are never wiped.
 *
 *   php bin/setup_db.php            as above
 *   php bin/setup_db.php --reset    DROP everything and start again (asks for nothing: be careful)
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

require __DIR__ . '/../src/bootstrap.php';

use PassGo\Database;

$reset = in_array('--reset', $argv, true);
$pdo = Database::pdo();

$exists = (bool) $pdo->query("SELECT to_regclass('public.users') IS NOT NULL")->fetchColumn();

if ($reset || !$exists) {
    echo $exists ? "Resetting database...\n" : "Empty database: creating tables and demo data...\n";
    $pdo->exec(file_get_contents(PASSGO_ROOT . '/database/schema.sql'));
    $pdo->exec(file_get_contents(PASSGO_ROOT . '/database/seed.sql'));
    echo "Done.\n";
} else {
    $pdo->exec(file_get_contents(PASSGO_ROOT . '/database/topup_slots.sql'));
    $n = (int) $pdo->query("SELECT count(*) FROM time_slots WHERE starts_at > now()")->fetchColumn();
    echo "Database already set up. Future time slots available: $n\n";
}
