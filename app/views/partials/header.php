<?php
/**
 * Top header (design-system layout-skeleton.html).
 *
 * Theme-critical ids kept verbatim because the theme JS binds to them:
 *   #mobile-collapse   toggles .mob-navigation-active on nav.nxl-navigation
 *   #menu-mini-button  toggles .minimenu on <html>
 *   .dark-button / .light-button  drive the persisted dark skin
 *
 * Expected: $user
 */
$initial = strtoupper(mb_substr($user['display_name'], 0, 1));
?>
<header class="nxl-header" id="top-header">
    <div class="header-wrapper">
        <div class="header-left d-flex align-items-center gap-4">
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                        <div class="hamburger-inner"></div>
                    </div>
                </div>
            </a>
            <div class="nxl-navigation-toggle">
                <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
            </div>
        </div>

        <div class="header-right ms-auto">
            <div class="d-flex align-items-center">
                <div class="nxl-h-item d-none d-sm-flex">
                    <div class="full-screen-switcher">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                            <i class="feather-maximize maximize"></i>
                            <i class="feather-minimize minimize"></i>
                        </a>
                    </div>
                </div>

                <div class="nxl-h-item dark-light-theme">
                    <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button"><i class="feather-moon"></i></a>
                    <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none"><i class="feather-sun"></i></a>
                </div>

                <div class="dropdown nxl-h-item" id="header-user-menu">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <div class="avatar-text avatar-md bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                             style="width:38px;height:38px;"><?= e($initial) ?></div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="text-dark mb-0">
                                        <?= e($user['display_name']) ?>
                                        <span class="badge bg-soft-primary text-primary ms-1"><?= e(ucfirst($user['role'])) ?></span>
                                    </h6>
                                    <span class="fs-12 fw-medium text-muted"><?= e($user['email']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="/settings/security.php" class="dropdown-item" id="header-security-link">
                            <i class="feather-shield"></i><span>Security &amp; 2FA</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <!--
                          Logout is POST + CSRF, never a GET link: a GET logout is
                          itself a CSRF vector (<img src="/logout">). Full page
                          navigation, so no hx-* attributes here.
                        -->
                        <form method="post" action="/logout.php" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start" id="header-logout-btn">
                                <i class="feather-log-out"></i><span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
