-- Tenant database: MaluDB activity-memory setup + ingestion wiring.
--
-- NOTE: the exact extension DDL follows the installed MaluDB version's documentation at
-- deploy time (slice-0 environment gate verifies it installs cleanly). This file fixes
-- the *contract*: what feeds MaluDB, and how ingestion tracks progress.

-- CREATE EXTENSION IF NOT EXISTS maludb;   -- per the MaluDB install docs for the deployed version

-- Ingestion contract:
--   * Source: activity_log rows WHERE ingested_at IS NULL, in id order (the partial
--     index activity_log_pending_idx makes this cheap).
--   * The ingestion job (systemd timer, runs continuously) ships each batch into MaluDB,
--     then stamps ingested_at. At-least-once semantics; MaluDB dedupes on (tenant, id).
--   * The job runs per tenant database and also covers zobifit_control's activity_log.
--   * Nothing ever deletes from activity_log; retention/pruning is a MaluDB-side concern
--     after ingestion (activity memory cannot be backfilled — the log is the source).

-- The view the ingestion job reads (stable shape even if activity_log grows columns):
CREATE VIEW activity_log_pending AS
SELECT id, occurred_at, actor_user_id, actor_role, action, screen,
       entity, entity_id, before_data, after_data, ip, request_id
FROM activity_log
WHERE ingested_at IS NULL
ORDER BY id;
