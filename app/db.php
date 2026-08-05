<?php
declare(strict_types=1);

/**
 * The single PDO connection.
 *
 * Credentials come from config/database.php, which lives outside the document
 * root and is excluded from git. Record memory (the `app` schema) and activity
 * memory (`maludb_core`) share one database, so one connection reaches both —
 * the role's server-side search_path is `app, maludb_core, public`.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require dirname(__DIR__) . '/config/database.php';

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $config['host'],
        $config['port'],
        $config['dbname']
    );

    $pdo = new PDO($dsn, $config['user'], $config['password'], $config['options']);

    return $pdo;
}
