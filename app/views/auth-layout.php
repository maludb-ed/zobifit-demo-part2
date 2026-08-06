<?php
/**
 * Minimal auth layout (design-system auth-layout.html).
 *
 * Auth pages have NO sidebar, header or container — just
 * main.auth-minimal-wrapper > .auth-minimal-inner > .minimal-card-wrapper > .card.
 * They also carry no command bar: there is no session yet for the assistant to
 * act within.
 *
 * Expected: $title, $content
 */
$title = $title ?? 'Sign in';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Zobifit</title>

    <link rel="shortcut icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="stylesheet" type="text/css" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="/assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="/assets/css/theme.min.css">
    <!-- Auth pages keep ordinary page scrolling — no fixed chrome to scroll
         under — but take the shared scrollbar appearance and reserved gutter. -->
    <link rel="stylesheet" type="text/css" href="/assets/css/app-shell.css">
</head>

<body>
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card mb-4 mt-5 mx-4 mx-sm-0 position-relative">
                    <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-0 start-50">
                        <img src="/assets/images/logo-abbr.png" alt="Zobifit" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5">
                        <?= $content ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="/assets/vendors/js/vendors.min.js"></script>
    <script src="/assets/js/common-init.min.js"></script>
    <script src="/assets/js/theme-customizer-init.min.js"></script>
</body>

</html>
