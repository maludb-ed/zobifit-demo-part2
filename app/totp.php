<?php
declare(strict_types=1);

use OTPHP\TOTP;

/**
 * Authenticator-app 2FA (RFC 6238), per the php-session-auth totp-2fa reference.
 *
 * 2FA is a property of the ACCOUNT, not the login method — a Google sign-in
 * hits exactly the same challenge as a password sign-in.
 */

const TOTP_ISSUER          = 'Zobifit';
const TOTP_WINDOW          = 1;    // ±1 timestep. Never widen this to paper over clock drift.
const TOTP_RECOVERY_COUNT  = 10;

// ---------------------------------------------------------------------------
// Secret storage — encrypted at rest so a database dump yields no working seeds
// ---------------------------------------------------------------------------

function totp_encrypt_secret(string $secret): string
{
    $key   = base64_decode((string) app_config('totp_key'), true);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, $key));
}

function totp_decrypt_secret(string $stored): ?string
{
    $key = base64_decode((string) app_config('totp_key'), true);
    $raw = base64_decode($stored, true);

    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }

    $nonce      = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain      = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

    return $plain === false ? null : $plain;
}

// ---------------------------------------------------------------------------
// Enrollment
// ---------------------------------------------------------------------------

/**
 * A fresh secret. Stays in the session until a valid code confirms it — never
 * written to the database on the strength of "the user opened the page".
 *
 * 160 bits, the RFC 4226 recommendation, which is 32 base32 characters.
 * otphp's own generator defaults to 512 bits: no less secure, but it renders a
 * 103-character manual-entry key that nobody can reasonably type.
 */
function totp_new_secret(): string
{
    return ParagonIE\ConstantTime\Base32::encodeUpperUnpadded(random_bytes(20));
}

function totp_provisioning_uri(string $secret, string $email): string
{
    $totp = TOTP::createFromSecret($secret);
    $totp->setLabel($email);
    $totp->setIssuer(TOTP_ISSUER);
    return $totp->getProvisioningUri();
}

/**
 * QR as a data URI, rendered server-side. Never an external QR service — that
 * would hand the shared secret to a third party.
 */
function totp_qr_data_uri(string $provisioningUri): string
{
    $builder = new Endroid\QrCode\Builder\Builder(
        writer: new Endroid\QrCode\Writer\PngWriter(),
        data: $provisioningUri,
        size: 220,
        margin: 10
    );

    return $builder->build()->getDataUri();
}

// ---------------------------------------------------------------------------
// Verification
// ---------------------------------------------------------------------------

/**
 * Verify a code against a secret, with the replay guard.
 *
 * A six-digit code is reusable within its window unless the accepted timestep
 * is remembered, so a shoulder-surfed code stays valid for ~90 seconds. The
 * caller persists the returned timestep to `users.totp_last_timestep`.
 *
 * @return int|null The accepted timestep, or null when the code is invalid.
 */
function totp_verify(string $secret, string $code, ?int $lastTimestep): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return null;
    }

    $totp   = TOTP::createFromSecret($secret);
    $period = $totp->getPeriod();
    $now    = time();

    for ($offset = -TOTP_WINDOW; $offset <= TOTP_WINDOW; $offset++) {
        $timestamp = $now + ($offset * $period);
        $timestep  = (int) floor($timestamp / $period);

        // Replay guard: this code, or an older one, has already been spent.
        if ($lastTimestep !== null && $timestep <= $lastTimestep) {
            continue;
        }

        if (hash_equals($totp->at($timestamp), $code)) {
            return $timestep;
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// Recovery codes
// ---------------------------------------------------------------------------

/** Ten single-use codes, shown exactly once and stored only as hashes. */
function totp_generate_recovery_codes(): array
{
    $codes = [];
    for ($i = 0; $i < TOTP_RECOVERY_COUNT; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
    }
    return $codes;
}

function totp_store_recovery_codes(PDO $pdo, int $userId, array $codes): void
{
    $pdo->prepare('DELETE FROM totp_recovery_codes WHERE user_id = :id')
        ->execute(['id' => $userId]);

    $insert = $pdo->prepare(<<<'SQL'
        INSERT INTO totp_recovery_codes (user_id, code_hash) VALUES (:user_id, :hash)
    SQL);

    foreach ($codes as $code) {
        $insert->execute(['user_id' => $userId, 'hash' => password_hash($code, PASSWORD_DEFAULT)]);
    }
}

/**
 * Consume a recovery code. Each hash must be checked individually because
 * password_hash salts every row differently — there is nothing to look up by.
 */
function totp_consume_recovery_code(PDO $pdo, int $userId, string $code): bool
{
    $code = strtoupper(trim($code));

    $statement = $pdo->prepare(<<<'SQL'
        SELECT id, code_hash FROM totp_recovery_codes
        WHERE user_id = :id AND used_at IS NULL
    SQL);
    $statement->execute(['id' => $userId]);

    foreach ($statement->fetchAll() as $row) {
        if (password_verify($code, $row['code_hash'])) {
            $pdo->prepare('UPDATE totp_recovery_codes SET used_at = now() WHERE id = :id')
                ->execute(['id' => $row['id']]);
            return true;
        }
    }

    return false;
}

function totp_unused_recovery_count(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT count(*) FROM totp_recovery_codes WHERE user_id = :id AND used_at IS NULL
    SQL);
    $statement->execute(['id' => $userId]);
    return (int) $statement->fetchColumn();
}
