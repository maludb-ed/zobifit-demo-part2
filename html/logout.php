<?php
declare(strict_types=1);

/**
 * Logout — POST and CSRF-guarded.
 *
 * A GET logout is itself a CSRF vector: any page could sign the user out with
 * <img src="/logout.php">. Full page navigation, never an HTMX swap, so the
 * cleared session and fresh token land with the new document.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_post();
verify_csrf();

$user = current_user();
if ($user !== null) {
    log_activity(db(), 'logout', '/logout.php', 'users', (int) $user['id'], null, null, (int) $user['id'], $user['role']);
}

logout_session();

header('Location: /login.php');
exit;
