# Build spec: measurements slice

Exemplar to replicate: admin-catalogs. Tenant app.
Schema tables: measurement_entries, measurement_types — never modify them.
First slice with Chart.js — establishes the chart partial pattern all later slices copy
(init on htmx:afterSettle, destroy before swap-out; wiring ships in the Phase 2 shell).

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| measurements | /measurements/ | Per-type latest values + trend chart; entry history table |
| measurement-add | /measurements/new | Entry form: single measurement or DEXA batch |

Coach views a client's measurements at /measurements/?client={id} (same screens,
authorization: own data, or coach of that client).

## Measurements screen

- Type selector (select, grouped body/dexa, default weight) → swaps chart + history region
  (HTMX GET, explicit hx-push-url with query string).
- Chart: line, value over measured_on, goal line overlaid when an open measurement goal
  for that type exists. Canvas id measurements-chart.
- History table columns: date (medium), value + unit, source (badge: manual=info,
  dexa=dark), note, delete control (hx-confirm). Sort: measured_on desc. Page size 25.

## Form (measurement-add)

- Mode toggle (radio, not tabs): single | DEXA batch.
- Single: measurement_type_id (select) · value (number, required) · measured_on (date,
  default today) · note. Ids: measurement-form-field-{name}.
- DEXA batch: measured_on (date) · one number input per active dexa-category type (blank =
  skip) · note. Saves N entries, source='dexa', one transaction.
- Values entered in the unit shown (type's unit; weight converts per units_preference).

## Files (exactly these)

- public/measurements/index.php · form.php · save.php · delete.php
- app/features/measurements/queries.php
- app/views/measurements/page.php · partials/chart.php · table.php · row.php · form.php · saved.php

## Query functions

- find_measurement_entries(PDO, int clientId, int typeId, page=1): array
- find_measurement_series(PDO, int clientId, int typeId, ?string from, ?string to): array
- find_measurement_types_active(PDO): array
- insert_measurement_entry(PDO, int clientId, int typeId, float value, string date, string source, ?string note, int createdBy): array
- insert_dexa_batch(PDO, int clientId, array typeValues, string date, ?string note, int createdBy): array
- delete_measurement_entry(PDO, int id): bool

## Action manifest entries

- Screens: measurements, measurement-add.
- Actions: measurement_entry_create (undo: delete entry). Deletes are screen-only
  (hx-confirm), not voice actions.

## Activity log events

- screen_view; measurement_created (after_data incl. type+value), dexa_batch_created
  (after_data = the batch), measurement_deleted (before_data).

## Status vocabulary

- source badges: manual → info · dexa → dark.

## Out of scope

- DEXA PDF import (PLAN §9.8), measurement-type CRUD (admin catalog owns it; custom types
  ship later), goal creation (goals slice), progress-photo uploads.

## Open Questions

- (none)
