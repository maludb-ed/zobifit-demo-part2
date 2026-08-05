/*
 * Chart.js lifecycle for HTMX-swapped screens (PLAN.md §7).
 *
 * Charts live inside partials, so they cannot initialize on page load. Two
 * things have to happen or the app leaks canvases and shows ghost charts on
 * back-navigation:
 *
 *   1. initialize on htmx:afterSettle, once the swapped DOM is in place;
 *   2. destroy every instance in a region BEFORE it is swapped out.
 *
 * Built once here in the shell so every Phase 3 slice inherits it. A screen
 * declares a chart by rendering:
 *
 *   <canvas class="app-chart" data-chart-config='{"type":"line","data":{...}}'></canvas>
 */
(function () {
    'use strict';

    var instances = new Map();   // canvas element -> Chart instance

    function initCharts(scope) {
        if (!window.Chart) { return; }

        (scope || document).querySelectorAll('canvas.app-chart').forEach(function (canvas) {
            if (instances.has(canvas)) { return; }

            var raw = canvas.dataset.chartConfig;
            if (!raw) { return; }

            var config;
            try {
                config = JSON.parse(raw);
            } catch (error) {
                console.error('Invalid chart config on', canvas.id || canvas, error);
                return;
            }

            config.options = config.options || {};
            // Charts must not force horizontal page scroll at 375px.
            config.options.responsive = true;
            config.options.maintainAspectRatio = false;

            instances.set(canvas, new window.Chart(canvas, config));
        });
    }

    function destroyCharts(scope) {
        if (!scope) { return; }

        instances.forEach(function (chart, canvas) {
            if (scope === canvas || (scope.contains && scope.contains(canvas))) {
                chart.destroy();
                instances.delete(canvas);
            }
        });
    }

    // Destroy before the swap replaces the region.
    document.body.addEventListener('htmx:beforeSwap', function (event) {
        destroyCharts(event.detail.target);
    });

    // Initialize once the new DOM has settled.
    document.body.addEventListener('htmx:afterSettle', function (event) {
        initCharts(event.detail.target);
    });

    // And for charts present on a full page load.
    document.addEventListener('DOMContentLoaded', function () {
        initCharts(document);
    });

    window.appCharts = { init: initCharts, destroy: destroyCharts };
})();
