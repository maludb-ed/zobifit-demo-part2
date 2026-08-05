<?php
declare(strict_types=1);

/**
 * Dashboard controller — Pattern B (dual full-page / partial).
 *
 * A normal navigation gets the whole shell; an HTMX request gets just the
 * screen, which the sidebar swaps into #page-content.
 *
 * Sequence: bootstrap → authorize → query → log → render.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/dashboard/queries.php';

$user = require_login();
$pdo  = db();

$stats = match ($user['role']) {
    'admin'  => dashboard_admin_stats($pdo),
    'owner'  => dashboard_coach_stats($pdo, (int) $user['id'], true),
    'coach'  => dashboard_coach_stats($pdo, (int) $user['id'], false),
    default  => dashboard_client_stats($pdo, (int) $user['id']),
};

$activity = dashboard_recent_activity($pdo);

log_screen_view($pdo, '/');

$screenHtml = view('dashboard/page.php', [
    'user'      => $user,
    'stats'     => $stats,
    'activity'  => $activity,
    'roleLabel' => ucfirst($user['role']),
]);

// The command bar reads these to resolve "that" against the current screen.
$screenHtml = '<div data-screen="dashboard" data-entity="" data-record-id="">' . $screenHtml . '</div>';

if (is_htmx_request()) {
    header('Vary: HX-Request');   // stop caches confusing the two responses
    echo $screenHtml;
    exit;
}

echo view('layout.php', [
    'title'   => 'Dashboard',
    'content' => $screenHtml,
    'user'    => $user,
]);
