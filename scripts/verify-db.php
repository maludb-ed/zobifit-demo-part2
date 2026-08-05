<?php
/**
 * Slice-0 environment & connectivity gate (docs/PLAN.md §7a item 0).
 *
 * Proves the full path the stack depends on BEFORE any schema is applied or any
 * application code is written:
 *
 *   1. PHP has pdo_pgsql and can connect as the application role
 *   2. The server round-trips a query (SELECT version())
 *   3. The role can write / read / delete — permissions, not just reachability
 *   4. MaluDB is present and reachable, so activity memory has somewhere to go
 *
 * Runs identically from the CLI and over HTTP, so it also answers "does Apache
 * serve PHP and can the web user reach the database?".
 *
 *   php scripts/verify-db.php
 *   curl http://localhost/verify-db.php
 *
 * Exit code 0 = gate passed, 1 = gate failed.
 */

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$checks = [];
$failed = 0;

/** Record the outcome of one check. */
function check(string $name, callable $fn): void
{
    global $checks, $failed;
    try {
        $detail = $fn();
        $checks[] = ['ok' => true, 'name' => $name, 'detail' => $detail];
    } catch (Throwable $e) {
        $checks[] = ['ok' => false, 'name' => $name, 'detail' => $e->getMessage()];
        $failed++;
    }
}

$config = require __DIR__ . '/../config/database.php';
$pdo    = null;

check('pdo_pgsql extension loaded', function () {
    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('pdo_pgsql is not loaded');
    }
    return 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')';
});

check('connect as application role', function () use ($config, &$pdo) {
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $config['host'],
        $config['port'],
        $config['dbname']
    );
    $pdo = new PDO($dsn, $config['user'], $config['password'], $config['options']);

    $row = $pdo->query('select current_user, current_database()')->fetch();
    return "{$row['current_user']}@{$row['current_database']}";
});

check('server round-trips a query', function () use (&$pdo) {
    $version = $pdo->query('select version()')->fetchColumn();
    // "PostgreSQL 17.10 (Ubuntu ...)" -> "PostgreSQL 17.10"
    return implode(' ', array_slice(explode(' ', $version), 0, 2));
});

check('search_path resolves record + activity memory', function () use (&$pdo) {
    $path = $pdo->query('select current_setting($$search_path$$)')->fetchColumn();
    foreach (['app', 'maludb_core'] as $schema) {
        if (!str_contains($path, $schema)) {
            throw new RuntimeException("search_path is missing '$schema': $path");
        }
    }
    return $path;
});

check('write / read / delete on a scratch table', function () use (&$pdo) {
    // Named per-process so concurrent CLI and HTTP runs never collide.
    $table = 'gate_check_' . getmypid();

    $pdo->exec("create table app.$table (id bigint generated always as identity primary key, note text not null, created_at timestamptz not null default now())");
    try {
        $insert = $pdo->prepare("insert into app.$table (note) values (:note) returning id");
        $insert->execute(['note' => 'slice-0 gate']);
        $id = $insert->fetchColumn();

        $read = $pdo->prepare("select note from app.$table where id = :id");
        $read->execute(['id' => $id]);
        if ($read->fetchColumn() !== 'slice-0 gate') {
            throw new RuntimeException('read back the wrong value');
        }

        $delete = $pdo->prepare("delete from app.$table where id = :id");
        $delete->execute(['id' => $id]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('delete affected ' . $delete->rowCount() . ' rows');
        }
    } finally {
        $pdo->exec("drop table if exists app.$table");
    }

    return 'insert + select + delete OK (app schema)';
});

check('MaluDB extension available', function () use (&$pdo) {
    $row = $pdo->query("select extname, extversion from pg_extension where extname = 'maludb_core'")->fetch();
    if (!$row) {
        throw new RuntimeException('maludb_core is not installed in this database');
    }
    return "{$row['extname']} {$row['extversion']}";
});

// ---------------------------------------------------------------------------

$label  = $cli ? '' : '';
$width  = max(array_map(fn($c) => strlen($c['name']), $checks));
$lines  = ["Zobifit slice-0 connectivity gate", str_repeat('=', 34), ''];

foreach ($checks as $c) {
    $lines[] = sprintf(
        '[%s] %-' . $width . 's  %s',
        $c['ok'] ? 'PASS' : 'FAIL',
        $c['name'],
        $c['detail']
    );
}

$lines[] = '';
$lines[] = $failed === 0
    ? 'GATE PASSED — ' . count($checks) . '/' . count($checks) . ' checks OK'
    : "GATE FAILED — $failed of " . count($checks) . ' checks failed';

echo implode("\n", $lines), "\n";

if (!$cli && $failed > 0) {
    http_response_code(500);
}
exit($failed === 0 ? 0 : 1);
