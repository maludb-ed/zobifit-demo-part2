<?php
declare(strict_types=1);

/**
 * Per-session CSRF token.
 *
 * Rotation is login-time only: login and logout are full page navigations, so
 * the fresh token always reaches the new page's meta tag. Rotating mid-session
 * would strand already-swapped HTMX partials with a stale token source.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Discard the current token so the next csrf_token() call mints a fresh one. */
function rotate_csrf_token(): void
{
    unset($_SESSION['csrf_token']);
}

/**
 * Guard EVERY unsafe method, not POST alone — PUT/PATCH/DELETE would otherwise
 * bypass the check entirely. The token is accepted from the hidden form field
 * (full-page forms) or the X-CSRF-Token header (HTMX requests); both carry the
 * same session token.
 */
function verify_csrf(): void
{
    $unsafe = ['POST', 'PUT', 'PATCH', 'DELETE'];
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $unsafe, true)) {
        return;
    }

    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $sent)) {
        http_response_code(403);
        exit('CSRF validation failed.');
    }
}
