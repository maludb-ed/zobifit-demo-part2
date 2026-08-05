<?php
declare(strict_types=1);

/**
 * Login controller.
 *
 * Sequence: bootstrap → CSRF → throttle → authenticate → 2FA or session → log.
 * Every failure path returns the same generic message and records the attempt.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

send_no_store_headers();

// Already signed in? Nothing to do here.
if (is_logged_in()) {
    redirect('/');
}

// A failed Google round trip redirects back here with its (generic) reason.
$error = (string) ($_SESSION['login_error'] ?? '');
unset($_SESSION['login_error']);

$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $email    = normalize_email(request_string('email', 320));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = client_ip();
    $pdo      = db();

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif (too_many_attempts($pdo, $email, $ip)) {
        // Locked per-account AND per-IP. 429 so the response is honest about why.
        http_response_code(429);
        $error = 'Too many attempts. Try again in a few minutes.';
        log_activity($pdo, 'login_throttled', '/login.php', 'users', null, null, ['email' => $email], null, 'system');
    } else {
        $user = authenticate_password($pdo, $email, $password);
        record_attempt($pdo, $email, $ip, $user !== null);

        if ($user === null) {
            // Generic on purpose: never reveal which half was wrong.
            $error = 'Invalid email or password.';
            log_activity($pdo, 'login_failed', '/login.php', 'users', null, null, ['email' => $email], null, 'system');
        } elseif (user_has_2fa($user)) {
            begin_2fa_challenge($user);
            log_activity($pdo, 'login_2fa_challenged', '/login.php', 'users', (int) $user['id'], null, null, (int) $user['id'], $user['role']);
            redirect('/login-2fa.php');
        } else {
            establish_session($user);
            log_activity($pdo, 'login', '/login.php', 'users', (int) $user['id'], null, null, (int) $user['id'], $user['role']);
            redirect('/');
        }
    }
}

$content = view('auth/login.php', [
    'error'         => $error,
    'email'         => $email,
    'googleEnabled' => google_signin_enabled(),
]);

echo view('auth-layout.php', ['title' => 'Sign in', 'content' => $content]);
