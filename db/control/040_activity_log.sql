-- The activity log — the feed for MaluDB activity memory.
--
-- ONE log for the whole cluster. The earlier multi-database design carried an
-- identical table in the control database and in every tenant database so a
-- single MaluDB pipeline could ingest both streams; with one cluster per tenant
-- there is one schema, so there is one table, and `actor_role` spans platform
-- and coaching actors alike.
--
-- Logging is not optional and cannot be backfilled (PLAN.md §4). Every handler
-- that renders a screen or changes state inserts a row: when / who / what /
-- where / which / before / after. Assistant-performed actions log identically
-- (they go through the same PHP endpoints); the command bar's Undo resolves
-- inverse actions from this log, so `before_data` is load-bearing.

CREATE TABLE activity_log (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    occurred_at   timestamptz NOT NULL DEFAULT now(),
    actor_user_id bigint,              -- admins.id or users.id per actor_role; NULL = system
    actor_role    text NOT NULL
                  CHECK (actor_role IN ('admin', 'owner', 'coach', 'client', 'assistant', 'system')),
    action        text NOT NULL,       -- login, screen_view, food_log_created, goal_updated, ...
    screen        text,                -- route: /food-log/, /workouts/session/42, ...
    entity        text,                -- table-ish name: food_log_entries, goals, ...
    entity_id     bigint,
    before_data   jsonb,               -- prior values on update/delete (undo depends on these)
    after_data    jsonb,               -- new values on create/update
    ip            inet,
    request_id    text,
    ingested_at   timestamptz          -- set by the MaluDB shipper; NULL = pending
);
CREATE INDEX activity_log_occurred_idx ON activity_log (occurred_at);
CREATE INDEX activity_log_actor_idx    ON activity_log (actor_user_id, occurred_at);
CREATE INDEX activity_log_entity_idx   ON activity_log (entity, entity_id);
CREATE INDEX activity_log_pending_idx  ON activity_log (id) WHERE ingested_at IS NULL;
