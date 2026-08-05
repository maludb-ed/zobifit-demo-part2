<?php
declare(strict_types=1);

use League\OAuth2\Client\Provider\Google;

/**
 * Sign in with Google — server-side OIDC authorization-code flow.
 *
 * Deliberately not the Google JS button or One Tap: those inject third-party
 * JS and popup/iframe UI, which fights both the no-modal rule and CSP. The
 * login button is a plain link; everything else happens in PHP.
 */

function google_provider(): Google
{
    $config = app_config('google');

    return new Google([
        'clientId'     => $config['client_id'],
        'clientSecret' => $config['client_secret'],
        'redirectUri'  => rtrim((string) app_config('base_url'), '/') . '/auth/google/callback.php',
    ]);
}

/**
 * Resolve a verified Google identity to a local user, per the fixed linking
 * policy. The `sub` claim is the identity key; the email is display data,
 * because Google addresses can change.
 *
 * @return array{user: array|null, error: string|null}
 */
function google_resolve_account(PDO $pdo, string $sub, string $email, string $displayName): array
{
    $email = normalize_email($email);

    // 1. Known identity → that user.
    $statement = $pdo->prepare(<<<'SQL'
        SELECT u.id, u.email, u.display_name, u.role, u.status, u.totp_enabled_at
        FROM auth_identities i
        JOIN users u ON u.id = i.user_id
        WHERE i.provider = 'google' AND i.provider_user_id = :sub
    SQL);
    $statement->execute(['sub' => $sub]);
    $user = $statement->fetch();

    if ($user !== false) {
        return ['user' => $user, 'error' => null];
    }

    // 2. Same verified email on an existing user → auto-link.
    $statement = $pdo->prepare(<<<'SQL'
        SELECT u.id, u.email, u.display_name, u.role, u.status, u.totp_enabled_at,
               (SELECT count(*) FROM auth_identities x
                 WHERE x.user_id = u.id AND x.provider = 'google') AS google_identities
        FROM users u
        WHERE u.email = :email
    SQL);
    $statement->execute(['email' => $email]);
    $existing = $statement->fetch();

    if ($existing !== false) {
        // 4. Never merge accounts: a different Google identity already owns this user.
        if ((int) $existing['google_identities'] > 0) {
            return ['user' => null, 'error' => 'account_conflict'];
        }

        $pdo->prepare(<<<'SQL'
            INSERT INTO auth_identities (user_id, provider, provider_user_id, email_at_provider)
            VALUES (:user_id, 'google', :sub, :email)
        SQL)->execute(['user_id' => $existing['id'], 'sub' => $sub, 'email' => $email]);

        log_activity($pdo, 'identity_linked', '/auth/google/callback.php', 'users', (int) $existing['id'], null, ['provider' => 'google'], (int) $existing['id'], $existing['role']);

        unset($existing['google_identities']);
        return ['user' => $existing, 'error' => null];
    }

    /*
     * 3. No match at all.
     *
     * Zobifit is invite-only in v1 (PLAN.md §1), so — unlike the generic rule
     * in the skill — we do NOT create an account here. A Google sign-in from an
     * address nobody invited is refused.
     */
    return ['user' => null, 'error' => 'not_invited'];
}
