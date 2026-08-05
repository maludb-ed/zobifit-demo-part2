-- zobifit_control: tenant registry.
-- Billing is out of scope for v1 (PLAN.md §9.10) but attaches here later: subscription /
-- plan / invoice tables will reference tenants(id). status + plan_tier exist from day one;
-- application code gates on status, never on billing data.

CREATE TABLE tenants (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug           text NOT NULL UNIQUE,              -- subdomain: {slug}.zobifit.com
    name           text NOT NULL,                     -- the coaching business name
    status         text NOT NULL DEFAULT 'trial'
                   CHECK (status IN ('trial', 'active', 'suspended')),
    plan_tier      text NOT NULL DEFAULT 'standard',
    db_name        text NOT NULL UNIQUE,              -- zobifit_t_{slug}
    owner_email    text NOT NULL,                     -- first coach; invited on provisioning
    provisioned_at timestamptz,                       -- NULL until tenant DB is ready
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    CHECK (slug ~ '^[a-z][a-z0-9-]{1,40}$')
);
CREATE TRIGGER tenants_touch BEFORE UPDATE ON tenants
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
