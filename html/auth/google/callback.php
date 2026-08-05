<?php
declare(strict_types=1);

/**
 * Google OIDC callback.
 *
 * Deliberately hostile to replay: the stored state and PKCE verifier are
 * consumed BEFORE the token exchange, so a second delivery of the same
 * callback finds nothing to validate against and fails.
 *
 * Google sign-in does not bypass 2FA — an account with TOTP enabled lands in
 * the same pending challenge as a password sign-in.
 */

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/google.php';

send_no_store_headers();

if (!google_signin_enabled()) {
    http_response_code(404);
    exit('Google sign-in is not configured.');
}

$pdo = db();

/** Every failure looks the same to the user, and is logged with its real reason. */
$fail = static function (string $reason) use ($pdo): never {
    log_activity($pdo, 'login_google_failed', '/auth/google/callback.php', null, null, null, ['reason' => $reason], null, 'system');
    $_SESSION['login_error'] = 'Google sign-in could not be completed.';
    redirect('/login.php');
};

// Consume the one-shot values immediately.
$expectedState = $_SESSION['google_oauth_state']    ?? null;
$verifier      = $_SESSION['google_oauth_verifier'] ?? null;
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_verifier']);

$state = request_string('state', 128);
$code  = request_string('code', 512);

if ($expectedState === null || $state === '' || !hash_equals($expectedState, $state)) {
    $fail('state_mismatch');
}
if ($code === '') {
    $fail('missing_code');
}

$provider = google_provider();
if ($verifier !== null) {
    $provider->setPkceVerifier($verifier);
}

try {
    $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
    /** @var League\OAuth2\Client\Provider\GoogleUser $googleUser */
    $googleUser = $provider->getResourceOwner($token);
} catch (Throwable $exception) {
    error_log('Google token exchange failed: ' . $exception->getMessage());
    $fail('token_exchange');
}

$claims = $googleUser->toArray();

// An unverified Google email is treated as no email at all.
if (empty($claims['email_verified']) || empty($claims['email'])) {
    $fail('email_unverified');
}

$sub   = (string) ($claims['sub'] ?? '');
$email = (string) $claims['email'];
$name  = (string) ($claims['name'] ?? $email);

if ($sub === '') {
    $fail('missing_sub');
}

$resolved = google_resolve_account($pdo, $sub, $email, $name);

if ($resolved['user'] === null) {
    $fail($resolved['error'] ?? 'unresolved');
}

$user = $resolved['user'];

if (!in_array($user['status'], ['active', 'invited'], true)) {
    $fail('account_disabled');
}

record_attempt($pdo, normalize_email($email), client_ip(), true);

// Same convergence as password login — no path skips 2FA.
if (user_has_2fa($user)) {
    begin_2fa_challenge($user);
    log_activity($pdo, 'login_2fa_challenged', '/auth/google/callback.php', 'users', (int) $user['id'], null, ['method' => 'google'], (int) $user['id'], $user['role']);
    redirect('/login-2fa.php');
}

establish_session($user);
log_activity($pdo, 'login', '/auth/google/callback.php', 'users', (int) $user['id'], null, ['method' => 'google'], (int) $user['id'], $user['role']);

redirect('/');
