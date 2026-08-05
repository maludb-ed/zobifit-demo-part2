<?php
declare(strict_types=1);

/**
 * Dashboard queries.
 *
 * Phase 2 has no feature data yet, so these deliberately count real rows rather
 * than showing placeholders — a dashboard that reads zero is telling the truth,
 * and the same queries keep working as Phase 3 fills the tables.
 */

/** Admin: is the platform healthy, are the master catalogs complete? */
function dashboard_admin_stats(PDO $pdo): array
{
    $row = $pdo->query(<<<'SQL'
        SELECT
            (SELECT count(*) FROM master_muscle_groups WHERE is_active) AS muscle_groups,
            (SELECT count(*) FROM master_equipment     WHERE is_active) AS equipment,
            (SELECT count(*) FROM master_exercises     WHERE is_active) AS exercises,
            (SELECT count(*) FROM master_foods         WHERE is_active) AS foods
    SQL)->fetch();

    return [
        ['id' => 'muscle-groups', 'label' => 'Muscle Groups', 'value' => (int) $row['muscle_groups'], 'icon' => 'feather-activity', 'colour' => 'primary'],
        ['id' => 'equipment',     'label' => 'Equipment',     'value' => (int) $row['equipment'],     'icon' => 'feather-tool',     'colour' => 'info'],
        ['id' => 'exercises',     'label' => 'Exercises',     'value' => (int) $row['exercises'],     'icon' => 'feather-list',     'colour' => 'success'],
        ['id' => 'foods',         'label' => 'Foods',         'value' => (int) $row['foods'],         'icon' => 'feather-coffee',   'colour' => 'warning'],
    ];
}

/**
 * Coach: how are my clients doing right now?
 *
 * An owner sees the whole tenant; a staff coach sees only their own clients.
 * That distinction is enforced here rather than in the view, so a template can
 * never accidentally widen the scope.
 */
function dashboard_coach_stats(PDO $pdo, int $coachId, bool $isOwner): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT
            (SELECT count(*) FROM users
              WHERE role = 'client' AND status = 'active'
                AND (:is_owner OR assigned_coach_id = :coach_id))            AS active_clients,
            (SELECT count(*) FROM program_assignments WHERE status = 'active') AS active_programs,
            (SELECT count(*) FROM workout_sessions
              WHERE session_date > current_date - 7)                          AS sessions_this_week,
            (SELECT count(*) FROM goals WHERE status = 'open')                AS open_goals
    SQL);
    $statement->execute([
        'coach_id' => $coachId,
        'is_owner' => $isOwner ? 'true' : 'false',
    ]);
    $row = $statement->fetch();

    return [
        ['id' => 'active-clients',  'label' => 'Active Clients',    'value' => (int) $row['active_clients'],     'icon' => 'feather-users',    'colour' => 'primary'],
        ['id' => 'active-programs', 'label' => 'Active Programs',   'value' => (int) $row['active_programs'],    'icon' => 'feather-calendar', 'colour' => 'info'],
        ['id' => 'sessions-week',   'label' => 'Sessions (7 days)', 'value' => (int) $row['sessions_this_week'], 'icon' => 'feather-zap',      'colour' => 'success'],
        ['id' => 'open-goals',      'label' => 'Open Goals',        'value' => (int) $row['open_goals'],         'icon' => 'feather-flag',     'colour' => 'warning'],
    ];
}

/** Client: what's my workout, what's left to eat, am I on track? */
function dashboard_client_stats(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT
            (SELECT count(*) FROM workout_sessions
              WHERE client_user_id = :id AND session_date > current_date - 7) AS sessions_this_week,
            (SELECT count(*) FROM food_log_entries
              WHERE client_user_id = :id AND eaten_on = current_date)         AS foods_today,
            (SELECT count(*) FROM goals
              WHERE client_user_id = :id AND status = 'open')                 AS open_goals,
            (SELECT count(*) FROM program_assignments
              WHERE client_user_id = :id AND status = 'active')               AS active_programs
    SQL);
    $statement->execute(['id' => $userId]);
    $row = $statement->fetch();

    return [
        ['id' => 'sessions-week',   'label' => 'Workouts (7 days)',  'value' => (int) $row['sessions_this_week'], 'icon' => 'feather-zap',       'colour' => 'primary'],
        ['id' => 'foods-today',     'label' => 'Foods Logged Today', 'value' => (int) $row['foods_today'],        'icon' => 'feather-clipboard', 'colour' => 'info'],
        ['id' => 'active-programs', 'label' => 'Active Programs',    'value' => (int) $row['active_programs'],    'icon' => 'feather-calendar',  'colour' => 'success'],
        ['id' => 'open-goals',      'label' => 'Open Goals',         'value' => (int) $row['open_goals'],         'icon' => 'feather-flag',      'colour' => 'warning'],
    ];
}

/**
 * Recent activity, straight from the activity log.
 *
 * This is the activity memory made visible: the same rows MaluDB ingests, which
 * is why the dashboard has something real to show from the very first request.
 */
function dashboard_recent_activity(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min($limit, 50));

    $statement = $pdo->prepare(<<<'SQL'
        SELECT a.id, a.occurred_at, a.actor_role, a.action, a.screen, a.entity,
               u.display_name
        FROM activity_log a
        LEFT JOIN users u ON u.id = a.actor_user_id
        ORDER BY a.occurred_at DESC
        LIMIT :limit
    SQL);
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}
