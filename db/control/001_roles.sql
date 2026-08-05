-- Zobifit cluster roles. Run once per cluster, as a superuser, before creating databases.
-- Passwords are set at deploy time from environment config: ALTER ROLE ... PASSWORD ...

-- Application role: full DML on control + tenant databases, no DDL ownership.
CREATE ROLE zobifit_app LOGIN;

-- Read-only MCP roles. The MCP server processes hold ONLY these credentials.
CREATE ROLE zobifit_mcp_records_ro LOGIN;
CREATE ROLE zobifit_mcp_activity_ro LOGIN;

ALTER ROLE zobifit_mcp_records_ro  SET default_transaction_read_only = on;
ALTER ROLE zobifit_mcp_activity_ro SET default_transaction_read_only = on;
ALTER ROLE zobifit_mcp_records_ro  SET statement_timeout = '5s';
ALTER ROLE zobifit_mcp_activity_ro SET statement_timeout = '5s';

-- ---------------------------------------------------------------------------
-- Per-database grants. Run this block connected to EACH database after its
-- schema files are applied (control DB and every new tenant DB — step 4 of
-- tenant provisioning in db/README.md).
-- ---------------------------------------------------------------------------
-- GRANT CONNECT ON DATABASE :dbname TO zobifit_app, zobifit_mcp_records_ro, zobifit_mcp_activity_ro;
-- GRANT USAGE ON SCHEMA public TO zobifit_app, zobifit_mcp_records_ro, zobifit_mcp_activity_ro;
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO zobifit_app;
-- GRANT SELECT ON ALL TABLES IN SCHEMA public TO zobifit_mcp_records_ro;
-- GRANT SELECT ON activity_log TO zobifit_mcp_activity_ro;
-- ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO zobifit_app;
-- ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO zobifit_mcp_records_ro;
