<?php
declare(strict_types=1);

/**
 * Security settings controller — 2FA enrollment and removal.
 *
 * The candidate secret lives in the SESSION until a valid code proves the user
 * actually scanned it. Writing it to the database on page load would leave
 * accounts half-enrolled whenever someone opened the page and wandered off.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_login();
$pdo  = db();

$error  = '';
$notice = '';
$recoveryCodes = [];

$statement = $pdo->prepare('SELECT totp_secret, totp_enabled_at, totp_last_timestep FROM users WHERE id = :id');
$statement->execute(['id' => $user['id']]);
$totp = $statement->fetch();

$enabled = !empty($totp['totp_enabled_at']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $action = request_string('action', 16);
    $code   = request_string('code', 64);

    if ($action === 'enable' && !$enabled) {
        $candidate = $_SESSION['totp_candidate_secret'] ?? null;

        if ($candidate === null) {
            $error = 'That enrollment expired. Scan the new code below and try again.';
        } elseif (totp_verify($candidate, $code, null) === null) {
            $error = 'That code is not valid. Check your authenticator and try again.';
        } else {
            $recoveryCodes = totp_generate_recovery_codes();

            $pdo->beginTransaction();
            try {
                $pdo->prepare(<<<'SQL'
                    UPDATE users
                    SET totp_secret = :secret, totp_enabled_at = now(), totp_last_timestep = NULL
                    WHERE id = :id
                SQL)->execute([
                    'secret' => totp_encrypt_secret($candidate),
                    'id'     => $user['id'],
                ]);

                totp_store_recovery_codes($pdo, (int) $user['id'], $recoveryCodes);
                log_activity($pdo, 'totp_enabled', '/settings/security.php', 'users', (int) $user['id']);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($exception->getMessage());
                $recoveryCodes = [];
                $error = 'Two-factor could not be enabled. Try again.';
            }

            if ($error === '') {
                unset($_SESSION['totp_candidate_secret']);
                $enabled = true;
                $notice  = 'Two-factor authentication is on.';
            }
        }
    } elseif ($action === 'disable' && $enabled) {
        $secret   = $totp['totp_secret'] !== null ? totp_decrypt_secret($totp['totp_secret']) : null;
        $lastStep = $totp['totp_last_timestep'] === null ? null : (int) $totp['totp_last_timestep'];

        $validCode = $secret !== null && totp_verify($secret, $code, $lastStep) !== null;
        $validRecovery = !$validCode && totp_consume_recovery_code($pdo, (int) $user['id'], $code);

        if (!$validCode && !$validRecovery) {
            $error = 'That code is not valid. Two-factor is still on.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(<<<'SQL'
                    UPDATE users
                    SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_timestep = NULL
                    WHERE id = :id
                SQL)->execute(['id' => $user['id']]);

                $pdo->prepare('DELETE FROM totp_recovery_codes WHERE user_id = :id')
                    ->execute(['id' => $user['id']]);

                log_activity($pdo, 'totp_disabled', '/settings/security.php', 'users', (int) $user['id']);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($exception->getMessage());
                $error = 'Two-factor could not be disabled. Try again.';
            }

            if ($error === '') {
                $enabled = false;
                $notice  = 'Two-factor authentication is off.';
            }
        }
    }
}

// Offer a fresh candidate secret whenever 2FA is off.
$secret    = '';
$qrDataUri = '';
if (!$enabled) {
    if (empty($_SESSION['totp_candidate_secret'])) {
        $_SESSION['totp_candidate_secret'] = totp_new_secret();
    }
    $secret    = $_SESSION['totp_candidate_secret'];
    $qrDataUri = totp_qr_data_uri(totp_provisioning_uri($secret, $user['email']));
}

log_screen_view($pdo, '/settings/security.php');

$screenHtml = view('settings/security.php', [
    'user'              => $user,
    'enabled'           => $enabled,
    'qrDataUri'         => $qrDataUri,
    'secret'            => $secret,
    'recoveryCodes'     => $recoveryCodes,
    'recoveryRemaining' => $enabled ? totp_unused_recovery_count($pdo, (int) $user['id']) : 0,
    'error'             => $error,
    'notice'            => $notice,
]);

$screenHtml = '<div data-screen="security-settings" data-entity="users" data-record-id="' . e((string) $user['id']) . '">'
            . $screenHtml . '</div>';

if (is_htmx_request()) {
    header('Vary: HX-Request');
    echo $screenHtml;
    exit;
}

echo view('layout.php', ['title' => 'Security', 'content' => $screenHtml, 'user' => $user]);
