<?php
declare(strict_types=1);

/**
 * Begin the Google OIDC flow.
 *
 * Generates a session-bound `state` (the CSRF guard for the OAuth round trip)
 * and a PKCE verifier, stores both in the session, and redirects to Google.
 */

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/google.php';

send_no_store_headers();

if (!google_signin_enabled()) {
    http_response_code(404);
    exit('Google sign-in is not configured.');
}

$provider = google_provider();

$authUrl = $provider->getAuthorizationUrl([
    'scope' => ['openid', 'email', 'profile'],
]);

$_SESSION['google_oauth_state']    = $provider->getState();
$_SESSION['google_oauth_verifier'] = $provider->getPkceVerifier();

header('Location: ' . $authUrl);
exit;
