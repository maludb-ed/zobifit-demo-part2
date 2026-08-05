<?php
/**
 * Set a new password from a reset link.
 *
 * Expected: $token, $valid, $error, $done
 */
?>
<h2 class="fs-20 fw-bolder mb-4">New password</h2>

<?php if ($done): ?>
    <h4 class="fs-13 fw-bold mb-2">Password changed</h4>
    <p class="fs-12 fw-medium text-muted" id="reset-done-message">You can sign in with your new password now.</p>
    <div class="mt-5">
        <a href="/login.php" class="btn btn-lg btn-primary w-100" id="reset-signin-btn">Sign in</a>
    </div>
<?php elseif (!$valid): ?>
    <h4 class="fs-13 fw-bold mb-2">That link doesn't work</h4>
    <p class="fs-12 fw-medium text-muted" id="reset-invalid-message">
        Reset links expire after an hour and can only be used once. Request a fresh one.
    </p>
    <div class="mt-5">
        <a href="/forgot-password.php" class="btn btn-lg btn-primary w-100" id="reset-request-btn">Request a new link</a>
    </div>
<?php else: ?>
    <h4 class="fs-13 fw-bold mb-2">Choose a new password</h4>
    <p class="fs-12 fw-medium text-muted">At least <?= e(AUTH_MIN_PASSWORD_LENGTH) ?> characters.</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger fs-12 py-2 mt-3 mb-0" id="reset-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

    <form method="post" action="/reset-password.php" class="w-100 mt-4 pt-2" id="reset-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="mb-4">
            <label for="reset-field-password" class="form-label fs-12 fw-semibold">New password</label>
            <input type="password" name="password" id="reset-field-password" class="form-control"
                   autocomplete="new-password" required autofocus>
        </div>
        <div class="mb-3">
            <label for="reset-field-password-confirm" class="form-label fs-12 fw-semibold">Confirm password</label>
            <input type="password" name="password_confirm" id="reset-field-password-confirm" class="form-control"
                   autocomplete="new-password" required>
        </div>
        <div class="mt-5">
            <button type="submit" class="btn btn-lg btn-primary w-100" id="reset-submit-btn">Change password</button>
        </div>
    </form>
<?php endif; ?>
