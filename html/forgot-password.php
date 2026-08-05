<?php
declare(strict_types=1);

/**
 * Request a password reset.
 *
 * The response is identical whether or not the address exists — otherwise this
 * form becomes an account-enumeration oracle, exactly like a login that says
 * "no such user".
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

send_no_store_headers();

$sent  = false;
$email = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $email = normalize_email(request_string('email', 320));
    $pdo   = db();

    if ($email === '') {
        $error = 'Enter your email address.';
    } else {
        $statement = $pdo->prepare('SELECT id, email, display_name FROM users WHERE email = :email AND status = :status');
        $statement->execute(['email' => $email, 'status' => 'active']);
        $user = $statement->fetch();

        if ($user !== false) {
            // Raw token to the user, hash to the database — a leaked table must
            // not yield usable reset links.
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $pdo->prepare(<<<'SQL'
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (:user_id, :hash, now() + interval '1 hour')
            SQL)->execute(['user_id' => $user['id'], 'hash' => $tokenHash]);

            $resetUrl = rtrim((string) app_config('base_url'), '/') . '/reset-password.php?token=' . $token;

            try {
                send_password_reset_email($user['email'], $user['display_name'], $resetUrl);
            } catch (Throwable $exception) {
                // Never surface mail-transport detail: it would confirm the
                // address exists. Log it and show the same generic result.
                error_log('Password reset mail failed: ' . $exception->getMessage());
            }

            log_activity($pdo, 'password_reset_requested', '/forgot-password.php', 'users', (int) $user['id'], null, null, null, 'system');
        }

        // Same message either way.
        $sent = true;
    }
}

$content = view('auth/forgot-password.php', ['sent' => $sent, 'email' => $email, 'error' => $error]);

echo view('auth-layout.php', ['title' => 'Reset password', 'content' => $content]);
