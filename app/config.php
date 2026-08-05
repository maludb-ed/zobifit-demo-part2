<?php
declare(strict_types=1);

/** Application config from config/app.php, loaded once. */
function app_config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

/**
 * Google sign-in is enabled only when real credentials are configured, so the
 * login page degrades cleanly to email/password on a fresh install rather than
 * offering a button that leads to an error.
 */
function google_signin_enabled(): bool
{
    $google = app_config('google') ?? [];
    return ($google['client_id'] ?? '') !== '' && ($google['client_secret'] ?? '') !== '';
}
