-- zobifit_control: activity log for admin/platform actions.
-- Identical shape to the tenant activity_log (150_activity_log.sql) so one MaluDB
-- ingestion pipeline handles both streams. See that file for column semantics.

CREATE TABLE activity_log (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    occurred_at   timestamptz NOT NULL DEFAULT now(),
    actor_user_id bigint,                             -- admins.id here; NULL = system
    actor_role    text NOT NULL CHECK (actor_role IN ('admin', 'assistant', 'system')),
    action        text NOT NULL,                      -- login, screen_view, tenant_created, ...
    screen        text,                               -- route, e.g. /tenants/
    entity        text,                               -- tenants, master_exercises, ...
    entity_id     bigint,
    before_data   jsonb,                              -- prior values on update/delete
    after_data    jsonb,                              -- new values on create/update
    ip            inet,
    request_id    text,
    ingested_at   timestamptz                         -- set by the MaluDB shipper
);
CREATE INDEX activity_log_occurred_idx ON activity_log (occurred_at);
CREATE INDEX activity_log_actor_idx    ON activity_log (actor_user_id, occurred_at);
CREATE INDEX activity_log_entity_idx   ON activity_log (entity, entity_id);
CREATE INDEX activity_log_pending_idx  ON activity_log (id) WHERE ingested_at IS NULL;
