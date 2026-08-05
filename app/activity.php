<?php
declare(strict_types=1);

/**
 * Activity memory — the feed for MaluDB (PLAN.md §4).
 *
 * Logging is not optional and cannot be backfilled, so it exists before the
 * first feature. Every handler that renders a screen or changes state writes a
 * row: when / who / what / where / which / before / after.
 *
 * Actions the assistant performs on a user's behalf log identically, because
 * they go through these same endpoints — and the command bar's Undo resolves
 * the inverse action from `before_data`, which makes that column load-bearing
 * rather than decorative.
 *
 * A logging failure must never break the user's request. The row is best-effort
 * and errors go to the PHP error log instead of the response.
 */
function log_activity(
    PDO $pdo,
    string $action,
    ?string $screen = null,
    ?string $entity = null,
    ?int $entityId = null,
    ?array $beforeData = null,
    ?array $afterData = null,
    ?int $actorUserId = null,
    ?string $actorRole = null
): void {
    try {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO activity_log
                (actor_user_id, actor_role, action, screen, entity, entity_id,
                 before_data, after_data, ip, request_id)
            VALUES
                (:actor_user_id, :actor_role, :action, :screen, :entity, :entity_id,
                 :before_data, :after_data, :ip, :request_id)
        SQL);

        $statement->execute([
            'actor_user_id' => $actorUserId ?? current_user_id(),
            'actor_role'    => $actorRole ?? current_actor_role(),
            'action'        => $action,
            'screen'        => $screen ?? ($_SERVER['REQUEST_URI'] ?? null),
            'entity'        => $entity,
            'entity_id'     => $entityId,
            'before_data'   => $beforeData === null ? null : json_encode($beforeData),
            'after_data'    => $afterData === null ? null : json_encode($afterData),
            'ip'            => client_ip(),
            'request_id'    => request_id(),
        ]);
    } catch (Throwable $exception) {
        error_log('activity_log write failed: ' . $exception->getMessage());
    }
}

/**
 * Convenience wrapper for the most common case: a screen was entered.
 * Called by every GET controller that renders a screen.
 */
function log_screen_view(PDO $pdo, string $screen): void
{
    log_activity($pdo, 'screen_view', $screen);
}

/**
 * The actor role for the log's CHECK constraint: admin / owner / coach /
 * client / assistant / system. Falls back to 'system' for unauthenticated
 * requests, which is how login attempts and cron jobs record themselves.
 */
function current_actor_role(): string
{
    if (!empty($_SESSION['assistant_acting'])) {
        return 'assistant';
    }
    $role = $_SESSION['user_role'] ?? null;
    $allowed = ['admin', 'owner', 'coach', 'client', 'assistant', 'system'];
    return in_array($role, $allowed, true) ? $role : 'system';
}
