<?php
declare(strict_types=1);

/**
 * Shared controller for Phase 2 navigation stubs.
 *
 * Each stub endpoint is three lines: bootstrap, set $screenId, include this.
 * Keeping the logic in one place means the authorization check, the activity
 * log row and the Pattern B dual response are identical on every stub — and
 * when a slice is built, its endpoint replaces the include rather than
 * diverging from a copy-pasted original.
 *
 * Expected before include: $screenId
 */

/** Which Phase 3 slice (PLAN.md §7a) delivers each screen. */
function stub_slice_for(string $screenId): ?string
{
    return [
        'muscle-groups'     => '1 — Admin catalogs',
        'equipment'         => '1 — Admin catalogs',
        'exercises'         => '1 — Admin catalogs',
        'foods'             => '1 — Admin catalogs',
        'measurement-types' => '1 — Admin catalogs',
        'clients'           => '2 — Clients & profiles',
        'client-invite'     => '2 — Clients & profiles',
        'measurements'      => '3 — Measurements & DEXA',
        'food-log'          => '4 — Food logging',
        'nutrition-goals'   => '4 — Food logging',
        'workout-templates' => '5 — Templates & programs',
        'programs'          => '5 — Templates & programs',
        'workouts'          => '6 — Guided workout',
        'progress'          => '7 — Progress & muscle balance',
        'muscle-balance'    => '7 — Progress & muscle balance',
        'goals'             => '8 — Goal setting & tracking',
    ][$screenId] ?? null;
}

$screen = find_screen($screenId);
if ($screen === null) {
    http_response_code(404);
    exit('Unknown screen');
}

// Authorization is checked on every request; HX-Request is never a signal.
$user = require_role(...$screen['roles']);
$pdo  = db();

log_screen_view($pdo, $screen['url']);

$screenHtml = view('shared/coming-soon.php', [
    'screen' => $screen,
    'slice'  => stub_slice_for($screenId),
]);

$screenHtml = sprintf(
    '<div data-screen="%s" data-entity="" data-record-id="">%s</div>',
    e($screen['id']),
    $screenHtml
);

if (is_htmx_request()) {
    header('Vary: HX-Request');
    echo $screenHtml;
    exit;
}

echo view('layout.php', [
    'title'   => $screen['label'],
    'content' => $screenHtml,
    'user'    => $user,
]);
