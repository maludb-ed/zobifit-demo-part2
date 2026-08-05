<?php
/**
 * Security settings — 2FA enrollment and management.
 *
 * A dedicated full page, per the no-modal rule. Three states:
 *   - 2FA off      → show the QR, the manual key, and a confirm field
 *   - just enabled → show the ten recovery codes ONCE
 *   - 2FA on       → show status and a disable form that demands a real code
 *
 * Expected: $user, $enabled, $qrDataUri, $secret, $recoveryCodes,
 *           $recoveryRemaining, $error, $notice
 */
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Security</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item">Account</li>
            <li class="breadcrumb-item">Security</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none">
                <a href="javascript:void(0)" class="page-header-right-close-toggle">
                    <i class="feather-arrow-left me-2"></i><span>Back</span>
                </a>
            </div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <span class="badge bg-soft-<?= $enabled ? 'success text-success' : 'secondary text-secondary' ?>">
                    <?= $enabled ? 'Two-factor on' : 'Two-factor off' ?>
                </span>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center">
            <a href="javascript:void(0)" class="page-header-right-open-toggle">
                <i class="feather-align-right fs-20"></i>
            </a>
        </div>
    </div>
</div>

<div class="main-content">
<?php if ($error !== ''): ?>
    <div class="alert alert-danger fs-12" id="security-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($notice !== ''): ?>
    <div class="alert alert-success fs-12" id="security-notice" role="alert"><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($recoveryCodes !== []): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full border-warning" id="security-recovery-codes">
                <div class="card-header">
                    <h5 class="card-title">Save your recovery codes</h5>
                </div>
                <div class="card-body">
                    <p class="fs-12 text-muted">
                        Each code works once, and this is the only time they are shown.
                        Store them somewhere you can reach without your phone.
                    </p>
                    <div class="row">
<?php foreach ($recoveryCodes as $code): ?>
                        <div class="col-6 col-md-3 mb-2">
                            <code class="fs-13"><?= e($code) ?></code>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Two-factor authentication</h5>
                </div>
                <div class="card-body">
<?php if (!$enabled): ?>
                    <p class="fs-12 text-muted">
                        Scan this with an authenticator app (Google Authenticator, 1Password, Authy),
                        then enter the six-digit code it shows to switch two-factor on.
                    </p>
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center mb-4 mb-md-0">
                            <img src="<?= e($qrDataUri) ?>" alt="Two-factor QR code" class="img-fluid" id="security-totp-qr">
                        </div>
                        <div class="col-md-8">
                            <div class="mb-4">
                                <label class="form-label fs-12 fw-semibold">Manual entry key</label>
                                <div><code class="fs-13" id="security-totp-secret"><?= e($secret) ?></code></div>
                            </div>
                            <form method="post" action="/settings/security.php" id="security-enable-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="enable">
                                <div class="row mb-3">
                                    <label for="security-field-code" class="col-lg-4 col-form-label fs-12 fw-semibold">Six-digit code</label>
                                    <div class="col-lg-8">
                                        <input type="text" name="code" id="security-field-code" class="form-control"
                                               placeholder="123456" inputmode="numeric" autocomplete="one-time-code" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="security-enable-btn">
                                    <i class="feather-shield me-2"></i><span>Turn on two-factor</span>
                                </button>
                            </form>
                        </div>
                    </div>
<?php else: ?>
                    <p class="fs-12 text-muted mb-4">
                        Two-factor is on. You have <strong><?= e($recoveryRemaining) ?></strong> unused recovery codes.
                    </p>
                    <!-- Disabling demands a current code, never just a click. -->
                    <form method="post" action="/settings/security.php" id="security-disable-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="disable">
                        <div class="row mb-3">
                            <label for="security-field-disable-code" class="col-lg-4 col-form-label fs-12 fw-semibold">
                                Confirm with a code
                            </label>
                            <div class="col-lg-8">
                                <input type="text" name="code" id="security-field-disable-code" class="form-control"
                                       placeholder="123456 or a recovery code" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger" id="security-disable-btn"
                                hx-confirm="Turn off two-factor authentication?">
                            <i class="feather-shield-off me-2"></i><span>Turn off two-factor</span>
                        </button>
                    </form>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
