-- MCP access tokens.
--
-- NOTE: `activity_log` used to be defined here as well, duplicating the copy in
-- control/040_activity_log.sql — one per database under the old multi-database
-- design. With one cluster per tenant there is a single schema and a single log
-- table, defined once in control/040_activity_log.sql, which runs before this
-- file. Do not re-create it here.

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
