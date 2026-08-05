# Zobifit database design (Phase 1)

Target: PostgreSQL 17 on Ubuntu 24.04. Designed complete, up front, for every planned
feature (see docs/PLAN.md). Nothing here is applied until the §7a slice-0 environment gate
passes (`scripts/verify-db.php`, written in Phase 2 after checkpoint approval).

## Databases

| Database | Contents | Files |
|---|---|---|
| `zobifit_control` | Tenant registry, platform admins, master catalogs, control activity log | `control/*.sql` in order |
| `zobifit_t_{slug}` (one per tenant) | Everything tenant-scoped: users, catalog copies, diet, training, measurements, goals, activity log | `tenant/*.sql` in order |

Tenant resolution is by **subdomain**: `{slug}.zobifit.com` → tenant DB `zobifit_t_{slug}`.
Admin app runs on `admin.zobifit.com` → `zobifit_control`. This matches the MCP endpoint
scheme (`https://{slug}.zobifit.com/mcp/records` / `/mcp/activity`).

## Provisioning a tenant

1. Insert the `tenants` row in `zobifit_control` (status `trial`).
2. `CREATE DATABASE zobifit_t_{slug}`, run `tenant/*.sql` in filename order.
3. Run the catalog sync job (below) to seed catalog copies.
4. Apply role grants (`control/001_roles.sql` bottom section) to the new database.
5. Mark `tenants.provisioned_at`.

## Catalog sync (control → tenant)

Master catalogs (muscle groups, equipment, exercises + muscle weightings, foods,
measurement types) live in `zobifit_control` and are **copied** into each tenant database.

- Tenant copies carry `master_id` (unique) + `synced_at`; the sync job upserts rows whose
  master `updated_at` > copy `synced_at`, across all tenant DBs.
- Master rows are **never hard-deleted** — `is_active = false` propagates on sync, so tenant
  FKs never break.
- Catalog-provenance rows are read-only inside a tenant; tenant/client additions are new
  rows with provenance `tenant`/`client` and `master_id NULL`. No merge conflicts by
  construction.
- Muscle groups and equipment are admin-only (no tenant provenance) per the plan.

## Conventions (locked)

- Primary keys: `bigint GENERATED ALWAYS AS IDENTITY`.
- Every table: `created_at`/`updated_at` (`timestamptz`, default `now()`); user-owned rows
  carry the owning user id. `updated_at` maintained by the shared `touch_updated_at()`
  trigger.
- **Canonical metric storage**: kg, cm, g, kcal in the database; `client_profiles.
  units_preference` drives display/input conversion in PHP. Measurement types carry their
  own unit (DEXA values arrive in the scan's unit).
- Emails stored normalized (`lower(trim(...))` in PHP before insert/lookup) with a UNIQUE
  constraint; never rely on the DB to normalize.
- History over overwrite: nutrition goals, program assignments, and goals are superseded by
  new rows, never edited in place, so "what was it in March?" stays answerable.
- Soft delete only for anything referenced by history (`is_active`), hard delete only for
  genuinely disposable rows (unaccepted invitations).

## Read-only MCP roles

`zobifit_mcp_records_ro` (record server) and `zobifit_mcp_activity_ro` (activity server)
are cluster roles with `SELECT`-only grants, `default_transaction_read_only = on`, and a
5-second `statement_timeout`. The MCP server processes hold only these credentials.
The PHP application connects as `zobifit_app` (full DML, no DDL).

## Apply order

```
control: 001_roles.sql → 010_admins.sql → 020_tenants.sql → 030_master_catalogs.sql → 040_activity_log.sql
tenant:  100_auth.sql → 110_catalogs.sql → 120_diet.sql → 130_training.sql
         → 140_measurements_goals.sql → 150_activity_log.sql → 160_maludb.sql
```
