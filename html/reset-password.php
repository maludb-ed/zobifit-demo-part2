<?php
declare(strict_types=1);

/**
 * Consume a reset token and set a new password.
 *
 * The token is looked up by its SHA-256 hash, must be unused, and must not have
 * expired. Consuming it also clears every login-attempt lockout for the account
 * — a user who proves control of the mailbox shouldn't stay locked out by the
 * failed guesses that sent them here.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

send_no_store_headers();

$token = request_string('token', 128);
$error = '';
$done  = false;

$pdo = db();

/** Resolve a raw token to its live reset row, or null. */
$findReset = static function (string $token) use ($pdo): ?array {
    if ($token === '') {
        return null;
    }
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.id, r.user_id, u.email, u.display_name, u.role
        FROM password_resets r
        JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = :hash
          AND r.used_at IS NULL
          AND r.expires_at > now()
    SQL);
    $statement->execute(['hash' => hash('sha256', $token)]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
};

$reset = $findReset($token);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if ($reset === null) {
        $error = 'That reset link is invalid or has expired.';
    } elseif ($password !== $confirm) {
        $error = 'Those passwords do not match.';
    } elseif (($problems = password_problems($password)) !== []) {
        $error = implode(' ', $problems);
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $reset['user_id']]);

            // Single use.
            $pdo->prepare('UPDATE password_resets SET used_at = now() WHERE id = :id')
                ->execute(['id' => $reset['id']]);

            // Invalidate any other outstanding links for this account.
            $pdo->prepare(<<<'SQL'
                UPDATE password_resets SET used_at = now()
                WHERE user_id = :id AND used_at IS NULL
            SQL)->execute(['id' => $reset['user_id']]);

            // Clear the lockout: mailbox control has been proven.
            $pdo->prepare('DELETE FROM login_attempts WHERE email = :email')
                ->execute(['email' => $reset['email']]);

            log_activity($pdo, 'password_reset_completed', '/reset-password.php', 'users', (int) $reset['user_id'], null, null, (int) $reset['user_id'], $reset['role']);
            $pdo->commit();
            $done = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            $error = 'The password could not be changed. Try again.';
        }
    }
}

$content = view('auth/reset-password.php', [
    'token' => $token,
    'valid' => $reset !== null,
    'error' => $error,
    'done'  => $done,
]);

echo view('auth-layout.php', ['title' => 'Set a new password', 'content' => $content]);
