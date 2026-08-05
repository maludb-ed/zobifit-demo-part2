<?php
declare(strict_types=1);

/**
 * MaluMail — the application's only outbound email channel (malumail-send).
 *
 * Credentials live in config/app.php, outside the document root. With no API
 * key configured the send is skipped and logged, so a fresh install can run the
 * whole reset flow without silently pretending mail went out.
 */

/**
 * Send one email. Returns the decoded API response.
 *
 * @throws RuntimeException on transport errors and non-2xx responses.
 */
function malumail_send(array $mail): array
{
    $apiKey = (string) (app_config('malumail')['api_key'] ?? '');

    if ($apiKey === '') {
        error_log('MaluMail not configured; skipped sending "' . ($mail['subject'] ?? '') . '" to '
            . (is_array($mail['to']) ? implode(', ', $mail['to']) : $mail['to']));
        return ['status' => 'skipped', 'accepted' => [], 'rejected' => []];
    }

    $ch = curl_init('https://api.malumail.com/v1/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($mail, JSON_THROW_ON_ERROR),
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno  = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        throw new RuntimeException('MaluMail transport error.');
    }

    $decoded = json_decode((string) $body, true);

    if ($status !== 200) {
        throw new RuntimeException('MaluMail send failed: HTTP ' . $status);
    }

    // Partial success is still 200 — a rejected address never gets delivered.
    if (!empty($decoded['rejected'])) {
        error_log('MaluMail rejected recipients: ' . json_encode($decoded['rejected']));
    }

    return $decoded;
}

/** The password-reset email. */
function send_password_reset_email(string $email, string $displayName, string $resetUrl): void
{
    $mail = app_config('malumail') ?? [];

    malumail_send([
        'from'      => $mail['from'] ?? 'noreply@zobifit.com',
        'from_name' => 'Zobifit',
        'to'        => $email,
        'subject'   => 'Reset your Zobifit password',
        'text'      => "Hi {$displayName},\n\n"
                     . "Use this link to set a new password. It expires in one hour and works once.\n\n"
                     . "{$resetUrl}\n\n"
                     . "If you didn't ask for this, you can ignore this email — nothing has changed.\n",
        'html'      => '<p>Hi ' . e($displayName) . ',</p>'
                     . '<p>Use this link to set a new password. It expires in one hour and works once.</p>'
                     . '<p><a href="' . e($resetUrl) . '">Set a new password</a></p>'
                     . '<p>If you didn\'t ask for this, you can ignore this email — nothing has changed.</p>',
    ]);
}
