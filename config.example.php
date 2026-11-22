<?php
// Copy this file to config/config.php and fill in your database details.
// Never commit config/config.php.
//
// Any value can also be provided through environment variables:
// PASSGO_DB_HOST, PASSGO_DB_PORT, PASSGO_DB_NAME, PASSGO_DB_USER,
// PASSGO_DB_PASSWORD, PASSGO_DB_SCHEMA  (used by the automated tests).

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 5432,
        'name'     => 'passgo',
        'user'     => 'passgo',
        'password' => 'change-me',
        // Optional: PostgreSQL schema to use (e.g. your own schema on the FEUP server).
        'schema'   => null,
    ],
    'app' => [
        'debug'    => false,
        'timezone' => 'Europe/Lisbon',
    ],
];
