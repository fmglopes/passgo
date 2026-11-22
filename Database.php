<?php
namespace PassGo;

use PDO;

/** One shared PDO connection to PostgreSQL. */
class Database
{
    private static $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $c['host'], (int) $c['port'], $c['name']);
            if (!empty($c['sslmode']) && preg_match('/^[a-z\-]+$/', (string) $c['sslmode'])) {
                $dsn .= ';sslmode=' . $c['sslmode']; // e.g. "require" for Neon
            }
            $pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET TIME ZONE 'Europe/Lisbon'");
            if (!empty($c['schema'])) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $c['schema'])) {
                    throw new \RuntimeException('Invalid schema name in configuration.');
                }
                $pdo->exec('SET search_path TO "' . $c['schema'] . '"');
            }
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }
}
