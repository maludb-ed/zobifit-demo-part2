-- Tenant identity.
--
-- TENANCY MODEL: one dedicated server cluster per tenant, so this is not a
-- registry of many tenants — it is the cluster's record of WHICH tenant it is,
-- constrained to exactly one row. The platform-level registry of all tenants
-- lives in the separate control cluster that provisions these deployments and
-- is not part of this schema.
--
-- `db_name` is gone: there is one database per cluster and the connection
-- already knows it. Billing is out of scope for v1 (PLAN.md §9.10) but attaches
-- in the control cluster later, keyed by `slug`; status + plan_tier exist here
-- from day one because application code gates on status, never on billing data.

CREATE TABLE tenants (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug           text NOT NULL UNIQUE,              -- subdomain: {slug}.zobifit.com
    name           text NOT NULL,                     -- the coaching business name
    status         text NOT NULL DEFAULT 'trial'
                   CHECK (status IN ('trial', 'active', 'suspended')),
    plan_tier      text NOT NULL DEFAULT 'standard',
    owner_email    text NOT NULL,                     -- first coach; invited on provisioning
    provisioned_at timestamptz,                       -- NULL until this cluster is ready
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    CHECK (slug ~ '^[a-z][a-z0-9-]{1,40}$')
);
CREATE TRIGGER tenants_touch BEFORE UPDATE ON tenants
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- Exactly one tenant per cluster. A second row is a provisioning bug, not a
-- feature — fail at insert rather than letting the app guess which one it is.
CREATE UNIQUE INDEX tenants_singleton ON tenants ((true));
