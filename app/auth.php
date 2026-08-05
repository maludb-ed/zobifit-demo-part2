<?php
declare(strict_types=1);

/**
 * Session authentication (php-session-auth skill).
 *
 * The cryptography here is the easy part; the security is the policy around it
 * — rate limits, email normalization, generic errors, and what we deliberately
 * do NOT do (no session binding to IP or User-Agent: it causes false logouts on
 * any network change and an attacker simply replays the header).
 */

/** Cost-matched dummy hash: verifying against it takes the same time as a real
 *  hash, so a missing account is indistinguishable from a wrong password.
 *  Regenerate if PASSWORD_DEFAULT's cost ever changes. */
const AUTH_DUMMY_HASH = '$2y$10$GeKlOLYN7dhx7R6reYdvyu3DUhRTHbf5a.N69nQ0/phbozowi1dOe';

const AUTH_MIN_PASSWORD_LENGTH = 12;
const AUTH_MAX_PASSWORD_BYTES  = 72;   // bcrypt silently truncates beyond this

const AUTH_MAX_ATTEMPTS_PER_EMAIL = 5;
const AUTH_MAX_ATTEMPTS_PER_IP    = 20;
const AUTH_ATTEMPT_WINDOW         = '15 minutes';

// ---------------------------------------------------------------------------
// Session state
// ---------------------------------------------------------------------------

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return is_int($id) ? $id : null;
}

function is_logged_in(): bool
{
    return current_user_id() !== null;
}

/** The signed-in user, loaded once per request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = current_user_id();
    if ($id === null) {
        return null;
    }

    $statement = db()->prepare(<<<'SQL'
        SELECT id, email, display_name, role, status, totp_enabled_at
        FROM users
        WHERE id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();

    $user = $row === false ? null : $row;
    return $user;
}

/**
 * Gate a screen behind a valid session. Full navigation, never a swap.
 *
 * Three distinct cases, and conflating them is a real bug: destroying the
 * session on every unauthenticated request wipes a pending 2FA challenge, so
 * any hit to a protected URL mid-challenge (a back-navigation, a background
 * tab, a stray prefetch) silently sends the user back to the start of login.
 */
function require_login(): array
{
    $user = current_user();

    // Signed in and permitted.
    if ($user !== null && in_array($user['status'], ['active', 'invited'], true)) {
        return $user;
    }

    // Half-authenticated: the challenge is still valid, so preserve it.
    if ($user === null && pending_2fa_user_id() !== null) {
        redirect('/login-2fa.php');
    }

    // A session pointing at a missing or disabled account is stale — clear it.
    if (current_user_id() !== null) {
        logout_session();
    }

    redirect('/login.php');
}

/**
 * Gate a screen behind one or more roles.
 * HX-Request is never an authorization signal — this runs on every request.
 */
function require_role(string ...$roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }

    return $user;
}

/**
 * Establish the session after ANY successful authentication — password, Google,
 * or a completed 2FA challenge. No path skips this.
 */
function establish_session(array $user): void
{
    session_regenerate_id(true);          // defeat session fixation
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    unset($_SESSION['pending_2fa_user_id']);
    rotate_csrf_token();                  // fresh token for the new privilege level
}

function logout_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Email + password rules
// ---------------------------------------------------------------------------

/** Normalize before BOTH insert and lookup, or User@x.com becomes a second account. */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/** @return string[] Validation errors; empty means acceptable. */
function password_problems(string $password): array
{
    $errors = [];
    if (strlen($password) < AUTH_MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . AUTH_MIN_PASSWORD_LENGTH . ' characters.';
    }
    if (strlen($password) > AUTH_MAX_PASSWORD_BYTES) {
        $errors[] = 'Password must be ' . AUTH_MAX_PASSWORD_BYTES . ' bytes or fewer.';
    }
    return $errors;
}

// ---------------------------------------------------------------------------
// Brute-force protection — per account AND per IP. Non-optional.
// ---------------------------------------------------------------------------

function too_many_attempts(PDO $pdo, string $email, string $ip): bool
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT
            count(*) FILTER (WHERE email = :email) AS email_failures,
            count(*) FILTER (WHERE ip = :ip)       AS ip_failures
        FROM login_attempts
        WHERE success = false
          AND attempted_at > now() - :window::interval
    SQL);
    $statement->execute([
        'email'  => $email,
        'ip'     => $ip,
        'window' => AUTH_ATTEMPT_WINDOW,
    ]);
    $row = $statement->fetch();

    return (int) $row['email_failures'] >= AUTH_MAX_ATTEMPTS_PER_EMAIL
        || (int) $row['ip_failures']    >= AUTH_MAX_ATTEMPTS_PER_IP;
}

function record_attempt(PDO $pdo, string $email, string $ip, bool $success): void
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO login_attempts (email, ip, success) VALUES (:email, :ip, :success)
    SQL);
    $statement->execute(['email' => $email, 'ip' => $ip, 'success' => $success ? 'true' : 'false']);
}

// ---------------------------------------------------------------------------
// Password authentication
// ---------------------------------------------------------------------------

/**
 * Verify credentials with constant-ish timing.
 *
 * Always runs password_verify — against the account's hash when it exists, and
 * against the cost-matched dummy when it doesn't — so response time never
 * reveals whether the address is registered.
 *
 * @return array|null The user row on success, null on any failure.
 */
function authenticate_password(PDO $pdo, string $email, string $password): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT id, email, password_hash, display_name, role, status, totp_enabled_at
        FROM users
        WHERE email = :email
    SQL);
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    $hash  = ($user !== false && $user['password_hash'] !== null)
        ? $user['password_hash']
        : AUTH_DUMMY_HASH;
    $valid = password_verify($password, $hash);

    if ($user === false || $user['password_hash'] === null || !$valid) {
        return null;
    }

    if (!in_array($user['status'], ['active', 'invited'], true)) {
        return null;
    }

    // Upgrade the stored hash if the cost or algorithm has moved on.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $update->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id'   => $user['id'],
        ]);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// Two-factor
// ---------------------------------------------------------------------------

function user_has_2fa(array $user): bool
{
    return !empty($user['totp_enabled_at']);
}

/**
 * Park the authentication half-finished until the TOTP challenge passes.
 * `user_id` is deliberately NOT set here — the session is not yet privileged.
 */
function begin_2fa_challenge(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
    $_SESSION['pending_2fa_since']   = time();
}

function pending_2fa_user_id(): ?int
{
    $id = $_SESSION['pending_2fa_user_id'] ?? null;
    // A challenge older than 10 minutes is stale; make the user start over.
    if ($id !== null && (time() - ($_SESSION['pending_2fa_since'] ?? 0)) > 600) {
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_since']);
        return null;
    }
    return is_int($id) ? $id : null;
}
