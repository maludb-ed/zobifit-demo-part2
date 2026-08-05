-- Zobifit roles and schema privileges.
--
-- TENANCY MODEL: one dedicated server cluster per tenant. The cluster IS the
-- tenant boundary, so there is exactly one database (`maludb`), one application
-- schema (`app`), and one application role (`app`). Nothing here creates roles
-- or databases — the MaluDB install provisions them, and this file only asserts
-- the preconditions and applies the grants Zobifit depends on.
--
-- Run once per cluster, as a superuser, before the schema files.
-- Safe to re-run.

-- ---------------------------------------------------------------------------
-- Preconditions — fail loudly rather than half-applying.
-- ---------------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app') THEN
        RAISE EXCEPTION 'Role "app" does not exist. This cluster is not MaluDB-provisioned.';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'app') THEN
        RAISE EXCEPTION 'Schema "app" does not exist. This cluster is not MaluDB-provisioned.';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'maludb_core') THEN
        RAISE EXCEPTION 'Extension "maludb_core" is not installed. Activity memory has nowhere to go.';
    END IF;
END
$$;

-- ---------------------------------------------------------------------------
-- Application role privileges.
--
-- `app` owns the `app` schema, so it already holds CREATE/USAGE there. These
-- grants are stated explicitly so the file is self-describing and survives a
-- cluster where ownership differs. `app` is deliberately NOT granted CREATE on
-- `public`: application objects belong in `app` and nowhere else.
-- ---------------------------------------------------------------------------
GRANT USAGE, CREATE ON SCHEMA app TO app;
GRANT USAGE ON SCHEMA maludb_core TO app;

-- Anything the schema files create is owned by `app` already; these defaults
-- cover objects created by a superuser during maintenance or migration.
ALTER DEFAULT PRIVILEGES IN SCHEMA app
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app;
ALTER DEFAULT PRIVILEGES IN SCHEMA app
    GRANT USAGE, SELECT ON SEQUENCES TO app;

-- ---------------------------------------------------------------------------
-- search_path.
--
-- `app, maludb_core, public` means unqualified application tables resolve to
-- the `app` schema and MaluDB functions resolve without qualification. Set on
-- the role so every connection inherits it — the PHP application, psql, and the
-- MCP server processes alike.
-- ---------------------------------------------------------------------------
ALTER ROLE app SET search_path = app, maludb_core, public;

-- ---------------------------------------------------------------------------
-- Read-only access for the MCP servers.
--
-- OPEN ITEM (see db/README.md): the earlier multi-database design gave the two
-- read MCP servers their own cluster roles. With one role per cluster that is
-- no longer automatic, and the servers currently have no separate identity.
-- Read-only enforcement therefore has to come from one of:
--   (a) a dedicated read-only role provisioned by MaluDB alongside `app`, or
--   (b) `maludb_read` (exists in the cluster, currently NOLOGIN), or
--   (c) the MCP process connecting as `app` inside a read-only transaction.
--
-- (c) is the weakest — it is a convention, not a privilege boundary — and is
-- NOT recommended for the client-facing servers, since SaaS Plus+ exposes those
-- endpoints to the tenant's own AI tools. Resolve before Phase 4.
-- ---------------------------------------------------------------------------
