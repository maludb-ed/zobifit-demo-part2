-- Tenant database: the activity log — the feed for MaluDB activity memory.
-- Logging is not optional and cannot be backfilled (PLAN.md §4). Every handler that
-- renders a screen or changes state inserts a row: when / who / what / where / which /
-- before / after. Assistant-performed actions log identically (they go through the same
-- PHP endpoints); the command bar's Undo resolves inverse actions from this log.

CREATE TABLE activity_log (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    occurred_at   timestamptz NOT NULL DEFAULT now(),
    actor_user_id bigint,                             -- users.id; NULL = system
    actor_role    text NOT NULL
                  CHECK (actor_role IN ('owner', 'coach', 'client', 'assistant', 'system')),
    action        text NOT NULL,      -- login, screen_view, food_log_created, goal_updated, ...
    screen        text,               -- route: /food-log/, /workouts/session/42, ...
    entity        text,               -- table-ish name: food_log_entries, goals, ...
    entity_id     bigint,
    before_data   jsonb,              -- prior values on update/delete (undo depends on these)
    after_data    jsonb,              -- new values on create/update
    ip            inet,
    request_id    text,
    ingested_at   timestamptz         -- set by the MaluDB shipper; NULL = pending
);
CREATE INDEX activity_log_occurred_idx ON activity_log (occurred_at);
CREATE INDEX activity_log_actor_idx    ON activity_log (actor_user_id, occurred_at);
CREATE INDEX activity_log_entity_idx   ON activity_log (entity, entity_id);
CREATE INDEX activity_log_pending_idx  ON activity_log (id) WHERE ingested_at IS NULL;

-- Per-client bearer tokens for the two client-facing read MCP servers (SaaS Plus+:
-- clients connect their own AI tools). Stored hashed; revocable; scope-limited.
CREATE TABLE mcp_access_tokens (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    label        text NOT NULL,                       -- "Edward's Claude Desktop"
    token_hash   text NOT NULL UNIQUE,
    scope        text NOT NULL DEFAULT 'both' CHECK (scope IN ('records', 'activity', 'both')),
    created_by   bigint NOT NULL REFERENCES users(id),
    created_at   timestamptz NOT NULL DEFAULT now(),
    last_used_at timestamptz,
    revoked_at   timestamptz
);
