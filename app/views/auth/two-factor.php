<?php
/**
 * 2FA challenge — a dedicated full page in the minimal auth style, never a
 * modal or an HTMX swap: promoting the session is a navigation boundary,
 * exactly like login.
 *
 * The same field accepts a 6-digit TOTP code or a recovery code, so a user
 * without their phone is not stuck on a screen that only offers one option.
 *
 * Expected: $error
 */
$error = $error ?? '';
?>
<h2 class="fs-20 fw-bolder mb-4">Two-factor</h2>
<h4 class="fs-13 fw-bold mb-2">Enter your authentication code</h4>
<p class="fs-12 fw-medium text-muted">Open your authenticator app, or use one of your recovery codes.</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger fs-12 py-2 mt-3 mb-0" id="auth-2fa-error" role="alert">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/login-2fa.php" class="w-100 mt-4 pt-2" id="auth-2fa-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="mb-4">
        <label for="auth-2fa-field-code" class="form-label fs-12 fw-semibold">Authentication code</label>
        <input type="text" name="code" id="auth-2fa-field-code" class="form-control"
               placeholder="123456" inputmode="numeric" autocomplete="one-time-code"
               required autofocus>
    </div>

    <div class="mt-5">
        <button type="submit" class="btn btn-lg btn-primary w-100" id="auth-2fa-submit-btn">Verify</button>
    </div>
</form>

<div class="mt-5 text-muted fs-12">
    <form method="post" action="/logout.php" class="d-inline m-0">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn btn-link p-0 fs-12 align-baseline" id="auth-2fa-cancel-btn">Cancel and sign in as someone else</button>
    </form>
</div>
