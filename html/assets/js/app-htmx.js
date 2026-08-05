/*
 * Application-wide HTMX wiring. Loaded on every page, after common-init.
 */
(function () {
    'use strict';

    /*
     * CSRF on every unsafe HTMX request. The token comes from the shell's meta
     * tag; the server accepts it from this header or a hidden form field.
     *
     * Rotation is login-time only, and login/logout are full page navigations,
     * so the meta tag is never stale relative to swapped-in partials.
     */
    document.body.addEventListener('htmx:configRequest', function (event) {
        if (event.detail.verb !== 'get') {
            var meta = document.querySelector('#csrf-token-meta');
            if (meta) {
                event.detail.headers['X-CSRF-Token'] = meta.content;
            }
        }
    });

    /*
     * The theme's own init runs on document-ready, so it never sees DOM that
     * arrived via a swap. Re-initialize the pieces that matter after each one.
     */
    document.body.addEventListener('htmx:afterSwap', function (event) {
        var scope = event.detail.target || document;

        // Bootstrap tooltips are opt-in per element.
        if (window.bootstrap && window.bootstrap.Tooltip) {
            scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                if (!window.bootstrap.Tooltip.getInstance(element)) {
                    new window.bootstrap.Tooltip(element);
                }
            });
        }

        // select2, where a swapped-in screen uses it.
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(scope).find('[data-select2-selector]').each(function () {
                if (!window.jQuery(this).hasClass('select2-hidden-accessible')) {
                    window.jQuery(this).select2();
                }
            });
        }
    });

    /*
     * Mark the active sidebar item after navigation. The server also stamps it
     * on a full page load; this keeps it right across HTMX swaps.
     */
    document.body.addEventListener('htmx:pushedIntoHistory', function () {
        var path = window.location.pathname;
        document.querySelectorAll('.nxl-navbar .nxl-item').forEach(function (item) {
            var link = item.querySelector('a.nxl-link');
            if (!link) { return; }
            item.classList.toggle('active', link.getAttribute('href') === path);
        });
    });

    /*
     * Close the mobile navigation drawer after navigating, otherwise it stays
     * open over the screen the user just asked for.
     */
    document.body.addEventListener('htmx:afterSwap', function (event) {
        if (event.detail.target && event.detail.target.id === 'page-content') {
            var nav = document.querySelector('nav.nxl-navigation');
            if (nav) { nav.classList.remove('mob-navigation-active'); }
            var overlay = document.querySelector('.nxl-menu-overlay');
            if (overlay) { overlay.remove(); }
        }
    });

    /* A failed swap must not fail silently. */
    document.body.addEventListener('htmx:responseError', function (event) {
        console.error('HTMX request failed:', event.detail.xhr.status, event.detail.pathInfo.requestPath);
    });
})();
