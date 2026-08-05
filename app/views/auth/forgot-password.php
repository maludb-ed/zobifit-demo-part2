<?php
/**
 * Request a reset link.
 *
 * Expected: $sent, $email, $error
 */
?>
<h2 class="fs-20 fw-bolder mb-4">Reset password</h2>

<?php if ($sent): ?>
    <h4 class="fs-13 fw-bold mb-2">Check your email</h4>
    <p class="fs-12 fw-medium text-muted" id="forgot-sent-message">
        If an account exists for that address, a reset link is on its way.
        The link expires in one hour and works once.
    </p>
    <div class="mt-5">
        <a href="/login.php" class="btn btn-lg btn-primary w-100" id="forgot-back-btn">Back to sign in</a>
    </div>
<?php else: ?>
    <h4 class="fs-13 fw-bold mb-2">We'll email you a link</h4>
    <p class="fs-12 fw-medium text-muted">Enter the address you sign in with.</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger fs-12 py-2 mt-3 mb-0" id="forgot-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

    <form method="post" action="/forgot-password.php" class="w-100 mt-4 pt-2" id="forgot-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-4">
            <label for="forgot-field-email" class="form-label fs-12 fw-semibold">Email</label>
            <input type="email" name="email" id="forgot-field-email" class="form-control"
                   value="<?= e($email) ?>" placeholder="you@example.com"
                   autocomplete="username" required autofocus>
        </div>
        <div class="mt-5">
            <button type="submit" class="btn btn-lg btn-primary w-100" id="forgot-submit-btn">Send reset link</button>
        </div>
    </form>

    <div class="mt-5 text-muted fs-12">
        <a href="/login.php" class="fw-bold" id="forgot-login-link">Back to sign in</a>
    </div>
<?php endif; ?>
