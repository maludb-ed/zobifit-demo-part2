<?php
declare(strict_types=1);

/**
 * The screen registry.
 *
 * This is deliberately one list serving two consumers: it renders the sidebar,
 * and it is the same registry the chat-actions `navigate` tool resolves against
 * (PLAN.md §6). A screen missing here is unreachable by voice — so new screens
 * get registered here as part of "done", not afterwards.
 *
 * Each entry: id (stable, kebab-case), label, url, icon, roles, and `when` —
 * the "when the user wants…" description the navigation router matches against.
 *
 * Phase 2 ships these as navigation stubs; Phase 3 fills them in slice order.
 */
function screen_registry(): array
{
    return [
        // --- Everyone -------------------------------------------------------
        ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => '/', 'icon' => 'feather-home',
         'group' => null, 'roles' => ['owner', 'coach', 'client', 'admin'],
         'when' => 'the user wants their home screen or an overview of where they stand'],

        // --- Admin: master catalogs ----------------------------------------
        ['id' => 'muscle-groups', 'label' => 'Muscle Groups', 'url' => '/catalogs/muscle-groups/', 'icon' => 'feather-activity',
         'group' => 'Catalogs', 'roles' => ['admin'],
         'when' => 'the admin wants to manage the muscle-group taxonomy'],
        ['id' => 'equipment', 'label' => 'Equipment', 'url' => '/catalogs/equipment/', 'icon' => 'feather-tool',
         'group' => 'Catalogs', 'roles' => ['admin'],
         'when' => 'the admin wants to manage the equipment catalog'],
        ['id' => 'exercises', 'label' => 'Exercise Library', 'url' => '/catalogs/exercises/', 'icon' => 'feather-list',
         'group' => 'Catalogs', 'roles' => ['admin'],
         'when' => 'the admin wants to manage exercises and their muscle weightings'],
        ['id' => 'foods', 'label' => 'Food Database', 'url' => '/catalogs/foods/', 'icon' => 'feather-coffee',
         'group' => 'Catalogs', 'roles' => ['admin'],
         'when' => 'the admin wants to manage the food database'],
        ['id' => 'measurement-types', 'label' => 'Measurement Types', 'url' => '/catalogs/measurement-types/', 'icon' => 'feather-thermometer',
         'group' => 'Catalogs', 'roles' => ['admin'],
         'when' => 'the admin wants to manage measurement and DEXA types'],

        // --- Coach: people --------------------------------------------------
        ['id' => 'clients', 'label' => 'Clients', 'url' => '/clients/', 'icon' => 'feather-users',
         'group' => 'Coaching', 'roles' => ['owner', 'coach'],
         'when' => 'the coach wants their client roster or one client\'s full picture'],
        ['id' => 'client-invite', 'label' => 'Invite Client', 'url' => '/clients/invite.php', 'icon' => 'feather-user-plus',
         'group' => 'Coaching', 'roles' => ['owner', 'coach'],
         'when' => 'the coach wants to invite a new client'],

        // --- Training -------------------------------------------------------
        ['id' => 'workouts', 'label' => 'Workouts', 'url' => '/workouts/', 'icon' => 'feather-zap',
         'group' => 'Training', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants today\'s workout, or to start or copy a workout'],
        ['id' => 'workout-templates', 'label' => 'Templates', 'url' => '/templates/', 'icon' => 'feather-layout',
         'group' => 'Training', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants the workout templates available to them'],
        ['id' => 'programs', 'label' => 'Programs', 'url' => '/programs/', 'icon' => 'feather-calendar',
         'group' => 'Training', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants training programs, the week-by-day grid, or to assign one'],

        // --- Diet -----------------------------------------------------------
        ['id' => 'food-log', 'label' => 'Food Log', 'url' => '/food-log/', 'icon' => 'feather-clipboard',
         'group' => 'Nutrition', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants their food diary, or to log something they ate'],
        ['id' => 'nutrition-goals', 'label' => 'Nutrition Goals', 'url' => '/nutrition-goals/', 'icon' => 'feather-target',
         'group' => 'Nutrition', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants calorie or macro goals, daily or weekly'],

        // --- Body & progress -------------------------------------------------
        ['id' => 'measurements', 'label' => 'Measurements', 'url' => '/measurements/', 'icon' => 'feather-trending-up',
         'group' => 'Progress', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants to record or view weight, tape measurements, or DEXA results'],
        ['id' => 'progress', 'label' => 'History & Progress', 'url' => '/progress/', 'icon' => 'feather-bar-chart-2',
         'group' => 'Progress', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants per-exercise charts, personal records, or volume trends'],
        ['id' => 'muscle-balance', 'label' => 'Muscle Balance', 'url' => '/muscle-balance/', 'icon' => 'feather-pie-chart',
         'group' => 'Progress', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants weekly training volume per muscle group'],
        ['id' => 'goals', 'label' => 'Goals', 'url' => '/goals/', 'icon' => 'feather-flag',
         'group' => 'Progress', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants their targets and how close they are'],
        ['id' => 'calculators', 'label' => 'Calculators', 'url' => '/calculators/', 'icon' => 'feather-percent',
         'group' => 'Progress', 'roles' => ['owner', 'coach', 'client'],
         'when' => 'the user wants BMI, BMR, calories burned, or a calorie target'],

        // --- Assistant -------------------------------------------------------
        ['id' => 'ama', 'label' => 'Ask Me Anything', 'url' => '/ama/', 'icon' => 'feather-message-circle',
         'group' => 'Assistant', 'roles' => ['owner', 'coach', 'client', 'admin'],
         'when' => 'the user wants to ask a free-form question about their data'],

        // --- Account ---------------------------------------------------------
        ['id' => 'security-settings', 'label' => 'Security', 'url' => '/settings/security.php', 'icon' => 'feather-shield',
         'group' => 'Account', 'roles' => ['owner', 'coach', 'client', 'admin'],
         'when' => 'the user wants to manage two-factor authentication or their password'],
    ];
}

/** The screens one role may see, grouped for the sidebar. */
function navigation_for_role(string $role): array
{
    $grouped = [];
    foreach (screen_registry() as $screen) {
        if (!in_array($role, $screen['roles'], true)) {
            continue;
        }
        $grouped[$screen['group'] ?? ''][] = $screen;
    }
    return $grouped;
}

/** Look up a screen by its stable id — what `navigate` resolves against. */
function find_screen(string $id): ?array
{
    foreach (screen_registry() as $screen) {
        if ($screen['id'] === $id) {
            return $screen;
        }
    }
    return null;
}
