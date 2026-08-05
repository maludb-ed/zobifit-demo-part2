<?php
declare(strict_types=1);

/**
 * The 2FA challenge.
 *
 * The session is deliberately NOT privileged while this page is in play:
 * `pending_2fa_user_id` is a distinct key and `user_id` stays unset, so every
 * authed page — which checks `user_id` — grants nothing until the code passes.
 *
 * Rate-limited exactly like password login: six digits fall to brute force in
 * hours otherwise.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

send_no_store_headers();

$pendingId = pending_2fa_user_id();
if ($pendingId === null) {
    redirect('/login.php');
}

$pdo   = db();
$error = '';

$statement = $pdo->prepare(<<<'SQL'
    SELECT id, email, display_name, role, status, totp_secret, totp_last_timestep
    FROM users WHERE id = :id
SQL);
$statement->execute(['id' => $pendingId]);
$user = $statement->fetch();

if ($user === false) {
    logout_session();
    redirect('/login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $code = request_string('code', 64);
    $ip   = client_ip();

    if (too_many_attempts($pdo, $user['email'], $ip)) {
        http_response_code(429);
        $error = 'Too many attempts. Try again in a few minutes.';
    } else {
        $secret   = $user['totp_secret'] !== null ? totp_decrypt_secret($user['totp_secret']) : null;
        $timestep = $secret !== null
            ? totp_verify($secret, $code, $user['totp_last_timestep'] === null ? null : (int) $user['totp_last_timestep'])
            : null;

        if ($timestep !== null) {
            // Spend this timestep so the same code cannot be replayed.
            $pdo->prepare('UPDATE users SET totp_last_timestep = :ts WHERE id = :id')
                ->execute(['ts' => $timestep, 'id' => $user['id']]);

            record_attempt($pdo, $user['email'], $ip, true);
            establish_session($user);
            log_activity($pdo, 'login_2fa', '/login-2fa.php', 'users', (int) $user['id'], null, ['method' => 'totp'], (int) $user['id'], $user['role']);
            redirect('/');
        }

        if (totp_consume_recovery_code($pdo, (int) $user['id'], $code)) {
            record_attempt($pdo, $user['email'], $ip, true);
            establish_session($user);

            $remaining = totp_unused_recovery_count($pdo, (int) $user['id']);
            $_SESSION['flash_warning'] = $remaining <= 3
                ? "Recovery code used. Only {$remaining} remain — generate new ones."
                : 'Recovery code used.';

            log_activity($pdo, 'login_2fa', '/login-2fa.php', 'users', (int) $user['id'], null, ['method' => 'recovery_code', 'remaining' => $remaining], (int) $user['id'], $user['role']);
            redirect('/');
        }

        record_attempt($pdo, $user['email'], $ip, false);
        $error = 'That code is not valid.';
        log_activity($pdo, 'login_2fa_failed', '/login-2fa.php', 'users', (int) $user['id'], null, null, null, 'system');
    }
}

$content = view('auth/two-factor.php', ['error' => $error]);

echo view('auth-layout.php', ['title' => 'Two-factor', 'content' => $content]);
