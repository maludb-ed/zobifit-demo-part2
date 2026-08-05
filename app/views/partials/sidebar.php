<?php
/**
 * Sidebar navigation, driven by the screen registry (app/navigation.php) so the
 * menu and the assistant's `navigate` tool can never drift apart.
 *
 * Structure is theme-critical: nav.nxl-navigation > .navbar-wrapper >
 * .m-header + .navbar-content > ul.nxl-navbar. nxlNavigation.min.js binds the
 * accordion behaviour and the mobile drawer to these exact classes, and gives
 * .navbar-content its PerfectScrollbar instance.
 *
 * Expected: $user
 */
$groups     = navigation_for_role($user['role']);
$currentUrl = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
?>
<nav class="nxl-navigation" id="left-sidenav">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="/" class="b-brand">
                <img src="/assets/images/logo-full.png" alt="Zobifit" class="logo logo-lg">
                <img src="/assets/images/logo-abbr.png" alt="Zobifit" class="logo logo-sm">
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
<?php foreach ($groups as $groupName => $screens): ?>
<?php if ($groupName === ''): ?>
                <li class="nxl-item nxl-caption"><label>Navigation</label></li>
<?php   foreach ($screens as $screen): ?>
                <li class="nxl-item<?= $currentUrl === $screen['url'] ? ' active' : '' ?>" id="nav-<?= e($screen['id']) ?>">
                    <a class="nxl-link" href="<?= e($screen['url']) ?>"
                       hx-get="<?= e($screen['url']) ?>"
                       hx-target="#page-content"
                       hx-swap="innerHTML"
                       hx-push-url="<?= e($screen['url']) ?>">
                        <span class="nxl-micon"><i class="<?= e($screen['icon']) ?>"></i></span>
                        <span class="nxl-mtext"><?= e($screen['label']) ?></span>
                    </a>
                </li>
<?php   endforeach; ?>
<?php else: ?>
                <li class="nxl-item nxl-caption"><label><?= e($groupName) ?></label></li>
<?php   foreach ($screens as $screen): ?>
                <li class="nxl-item<?= $currentUrl === $screen['url'] ? ' active' : '' ?>" id="nav-<?= e($screen['id']) ?>">
                    <!--
                      hx-push-url is an EXPLICIT canonical URL, never "true":
                      "true" pushes the request's own path, which moves the
                      document base and breaks every later relative URL.
                    -->
                    <a class="nxl-link" href="<?= e($screen['url']) ?>"
                       hx-get="<?= e($screen['url']) ?>"
                       hx-target="#page-content"
                       hx-swap="innerHTML"
                       hx-push-url="<?= e($screen['url']) ?>">
                        <span class="nxl-micon"><i class="<?= e($screen['icon']) ?>"></i></span>
                        <span class="nxl-mtext"><?= e($screen['label']) ?></span>
                    </a>
                </li>
<?php   endforeach; ?>
<?php endif; ?>
<?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>
