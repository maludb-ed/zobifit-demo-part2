<?php
declare(strict_types=1);

/**
 * Every endpoint's first line. Starts the session, loads the shared helpers.
 *
 * Nothing here queries the database or decides authorization — controllers do
 * that explicitly, so a missing check is visible in the controller rather than
 * hidden in a framework.
 */

// --- Errors: logged, never rendered. A stack trace in the response leaks the
// --- schema and the filesystem layout to anyone who can trigger an exception.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// --- Session hardening, before session_start().
$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');   // reject attacker-supplied session ids
ini_set('session.use_only_cookies', '1');
session_name('ZOBIFITSID');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/navigation.php';
