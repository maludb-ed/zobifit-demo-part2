# Zobifit database design (Phase 1)

Target: PostgreSQL 17 on Ubuntu 24.04, on a MaluDB-provisioned cluster.
Designed complete, up front, for every planned feature (see docs/PLAN.md).
The §7a slice-0 environment gate (`scripts/verify-db.php`) passes on this host.

## Tenancy model — one cluster per tenant

**The server cluster is the tenant boundary.** Each coaching business gets its
own dedicated PostgreSQL cluster, so within that cluster there is exactly:

| | |
|---|---|
| One database | `maludb` |
| One application schema | `app` |
| One application role | `app` (owns `app`, denied CREATE on `public`) |
| One activity log | `app.activity_log` |
| One tenant row | `app.tenants`, constrained to a single row |

`search_path` is set on the role to `app, maludb_core, public`, so unqualified
application tables resolve to `app` and MaluDB functions resolve unqualified.

This is a *stronger* form of the SaaS Plus+ sovereign-data promise than the
earlier draft, which separated tenants by database inside one shared cluster:
isolation is now physical, at the server, not logical, at the database. A tenant's
data never shares a process, a WAL, or a disk with another tenant's.

Tenant resolution is by **subdomain**: `{slug}.zobifit.com` routes to that
tenant's cluster; the cluster's single `tenants` row says which tenant it is.
MCP endpoints follow the same scheme (`https://{slug}.zobifit.com/mcp/records`
and `/mcp/activity`).

### What "control plane" means now

The `control/` files are **not** a separate database. They hold the
platform-level concerns — platform admins, the tenant identity row, and the
master catalogs — that used to live in a shared control database. They apply
into the same `app` schema as the `tenant/` files; the directory split is kept
only because it still describes *who owns* each table.

The genuine platform-wide registry of every tenant lives in a separate control
cluster that provisions these deployments. It is out of scope for this schema.

## Provisioning a tenant

1. Stand up a MaluDB-provisioned PostgreSQL 17 cluster (gives `maludb`, the
   `app` schema, the `app` role, and the `maludb_core` extension).
2. Run `control/001_roles.sql` as a superuser — asserts preconditions and
   applies grants and `search_path`. It creates no roles and no databases.
3. Run the remaining files in the order below as the `app` role.
4. Insert the single `tenants` row (status `trial`).
5. Run the catalog sync job to seed the master catalogs from the control cluster.
6. Set `tenants.provisioned_at`.

## Catalog sync (control cluster → tenant cluster)

Master catalogs (muscle groups, equipment, exercises + muscle weightings, foods,
measurement types) are authored on the platform control cluster and **copied**
into each tenant cluster's `master_*` tables, then into the working catalog
tables that carry provenance.

- Tenant copies carry `master_id` (unique) + `synced_at`; the sync job upserts
  rows whose master `updated_at` > copy `synced_at`.
- Master rows are **never hard-deleted** — `is_active = false` propagates on
  sync, so tenant FKs never break.
- Catalog-provenance rows are read-only inside a tenant; tenant/client additions
  are new rows with provenance `tenant`/`client` and `master_id NULL`. No merge
  conflicts by construction.
- Muscle groups and equipment are admin-only (no tenant provenance) per the plan.

> **Open design item.** Now that `master_*` and the working catalog tables share
> one schema, the two-table split buys less than it did across databases. It is
> retained because it keeps the sync contract and the provenance model intact,
> but collapsing them into single tables with a `provenance`/`master_id` pair is
> a defensible simplification. Decide before slice 1 builds against them.

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

## Read-only MCP roles — unresolved

The earlier design gave the record and activity MCP servers their own cluster
roles (`zobifit_mcp_records_ro`, `zobifit_mcp_activity_ro`) with SELECT-only
grants, `default_transaction_read_only = on`, and a 5-second `statement_timeout`.

One role per cluster removes that split. Since the client-facing MCP endpoints
are exposed to the tenant's own AI tools, read-only needs to be a *privilege*
boundary, not a convention. The options are listed at the bottom of
`control/001_roles.sql`. **Resolve before Phase 4.**

The PHP application connects as `app` (see `/var/www/config/database.php`).

## Apply order

All files apply into the `app` schema of the `maludb` database, in this order:

```
control: 001_roles.sql (superuser) → 010_admins.sql → 020_tenants.sql
         → 030_master_catalogs.sql → 040_activity_log.sql
tenant:  100_auth.sql → 110_catalogs.sql → 120_diet.sql → 130_training.sql
         → 140_measurements_goals.sql → 150_activity_log.sql → 160_maludb.sql
```

Verified 2026-08-05: all 11 files apply cleanly to PostgreSQL 17.10 into a
single schema, producing 42 tables.
