<?php
/**
 * Login form.
 *
 * A plain POST form, deliberately not HTMX: login is a full page navigation so
 * the regenerated session id and the fresh CSRF token reach the browser with
 * the new document.
 *
 * The error message is always generic — never "no such user" or "wrong
 * password" — so the form can't be used to enumerate accounts.
 *
 * Expected: $error, $email, $googleEnabled
 */
$error         = $error ?? '';
$email         = $email ?? '';
$googleEnabled = $googleEnabled ?? false;
?>
<h2 class="fs-20 fw-bolder mb-4">Zobifit</h2>
<h4 class="fs-13 fw-bold mb-2">Sign in to your account</h4>
<p class="fs-12 fw-medium text-muted">Coaching, training and nutrition in one place.</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger fs-12 py-2 mt-3 mb-0" id="login-error" role="alert">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/login.php" class="w-100 mt-4 pt-2" id="login-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="mb-4">
        <label for="login-field-email" class="form-label fs-12 fw-semibold">Email</label>
        <input type="email" name="email" id="login-field-email" class="form-control"
               value="<?= e($email) ?>" placeholder="you@example.com"
               autocomplete="username" required autofocus>
    </div>

    <div class="mb-3">
        <label for="login-field-password" class="form-label fs-12 fw-semibold">Password</label>
        <input type="password" name="password" id="login-field-password" class="form-control"
               placeholder="Your password" autocomplete="current-password" required>
    </div>

    <div class="d-flex align-items-center justify-content-end">
        <a href="/forgot-password.php" class="fs-11 text-primary" id="login-forgot-link">Forgot password?</a>
    </div>

    <div class="mt-5">
        <button type="submit" class="btn btn-lg btn-primary w-100" id="login-submit-btn">Sign in</button>
    </div>
</form>

<?php if ($googleEnabled): ?>
    <div class="w-100 mt-5 text-center mx-auto">
        <div class="mb-4 border-bottom position-relative">
            <span class="small py-1 px-3 text-uppercase text-muted bg-white position-absolute translate-middle">or</span>
        </div>
        <!-- Server-side OIDC redirect; no Google JS widget. -->
        <a href="/auth/google/start.php" class="btn btn-light-brand w-100" id="auth-login-google-btn">
            <i class="feather-log-in me-2"></i>
            <span>Sign in with Google</span>
        </a>
    </div>
<?php endif; ?>

<div class="mt-5 text-muted fs-12">
    <span>Zobifit is invite-only. Ask your coach for an invitation.</span>
</div>
