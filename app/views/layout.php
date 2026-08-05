<?php
/**
 * The application shell (design-system layout-skeleton.html).
 *
 * Structure is load-bearing: the `nxl-*` class names and the element order
 * (nav → header → main.nxl-container → footer → scripts) are what the theme
 * CSS and JS bind to. Asset order is fixed — theme.min.css last in <head>,
 * vendors.min.js first at the bottom, theme-customizer-init.min.js dead last
 * or dark mode silently stops working.
 *
 * Expected data:
 *   $title       string  page title
 *   $content     string  the .nxl-content inner HTML (page-header + main-content)
 *   $user        array   the signed-in user row
 *   $pageVendorCss  string[]  extra vendor CSS, inserted after vendors.min.css
 *   $pageVendorJs   string[]  extra vendor JS, inserted after vendors.min.js
 *   $pageInitJs     string[]  page init scripts, after common-init.min.js
 */
$title          = $title          ?? 'Zobifit';
$pageVendorCss  = $pageVendorCss  ?? [];
$pageVendorJs   = $pageVendorJs   ?? [];
$pageInitJs     = $pageInitJs     ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Zobifit — coaching, training and nutrition">

    <!-- CSRF: the shell is the single source the HTMX listener reads from. -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>" id="csrf-token-meta">

    <title><?= e($title) ?> | Zobifit</title>

    <link rel="shortcut icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="stylesheet" type="text/css" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="/assets/vendors/css/vendors.min.css">
<?php foreach ($pageVendorCss as $css): ?>
    <link rel="stylesheet" type="text/css" href="/assets/vendors/css/<?= e($css) ?>">
<?php endforeach; ?>
    <!-- theme.min.css ALWAYS last of the theme chain: it overrides the vendor bundle. -->
    <link rel="stylesheet" type="text/css" href="/assets/css/theme.min.css">
    <!-- Command-bar positioning only; the theme ships no styles for a component
         the design system nonetheless mandates on every screen. -->
    <link rel="stylesheet" type="text/css" href="/assets/css/app-assistant.css">
</head>

<body>
    <?= view('partials/sidebar.php', ['user' => $user]) ?>
    <?= view('partials/header.php', ['user' => $user]) ?>

    <main class="nxl-container">
        <!--
          HTMX SWAP TARGET. Screen partials carry their own .page-header AND
          .main-content and replace this whole region, so sidebar, header and
          footer sit safely outside and never get clobbered.
        -->
        <div class="nxl-content" id="page-content">
            <?= $content ?>
        </div>

        <footer class="footer" id="page-footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright &copy; <?= date('Y') ?> Zobifit</span>
            </p>
            <div class="d-flex align-items-center gap-4">
                <a href="/ama/" class="fs-11 fw-semibold text-uppercase">Ask Me Anything</a>
                <a href="/settings/security.php" class="fs-11 fw-semibold text-uppercase">Security</a>
            </div>
        </footer>
    </main>

    <?= view('partials/assistant-bar.php', []) ?>

    <!-- vendors.min.js must be first: jQuery, Bootstrap and PerfectScrollbar live in it. -->
    <script src="/assets/vendors/js/vendors.min.js"></script>
<?php foreach ($pageVendorJs as $js): ?>
    <script src="/assets/vendors/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
    <script src="/assets/vendors/js/htmx.min.js"></script>
    <script src="/assets/vendors/js/chart.umd.min.js"></script>

    <script src="/assets/js/common-init.min.js"></script>
    <script src="/assets/js/app-htmx.js"></script>
    <script src="/assets/js/app-charts.js"></script>
    <script src="/assets/js/app-assistant.js"></script>
<?php foreach ($pageInitJs as $js): ?>
    <script src="/assets/js/<?= e($js) ?>"></script>
<?php endforeach; ?>

    <!-- ALWAYS LAST: applies the persisted skin/font and drives the dark toggle. -->
    <script src="/assets/js/theme-customizer-init.min.js"></script>
</body>

</html>
