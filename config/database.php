<?php
/**
 * Database configuration for SQLite3 via PDO.
 */

define('DB_PATH', __DIR__ . '/../data/crm.sqlite');
define('DB_DIR', __DIR__ . '/../data');

/**
 * Get PDO connection instance.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(DB_DIR)) {
        mkdir(DB_DIR, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("PRAGMA encoding = 'UTF-8'");

    return $pdo;
}
