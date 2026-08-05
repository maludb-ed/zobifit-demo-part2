# Build spec: progress slice

Exemplar to replicate: measurements (chart pattern). Tenant app; every view accepts
?client={id} for coaches (authorization: self, or coach of that client).
Schema tables (read-only in this slice — no writes except activity log): logged_sets,
workout_sessions, exercises, exercise_muscles, muscle_groups, measurement_entries.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| progress | /progress/ | Exercise progression + volume trend + PRs |
| muscle-balance | /progress/muscle-balance | Weekly volume per muscle group |

## Progress screen

- Exercise selector (typeahead over exercises the client has logged) → swaps both charts
  (explicit hx-push-url ?exercise=).
- Chart 1 (progress-chart-exercise): best set per session over time (est. 1RM: weight ×
  (1 + reps/30), Epley — computed in SQL, same formula as the MCP tools).
- Chart 2 (progress-chart-volume): session volume by week — grouping select: by workout
  type (template) | total; overlays 4-week moving average.
- PR table: exercise, best weight × reps, est. 1RM, date — top 10 by recency; "new PR"
  badge (success) when within last 30 days.

## Muscle-balance screen

- Week navigation (prev/next ISO week, hx-push-url ?week=).
- Horizontal bar chart (muscle-balance-chart): volume per muscle group for the week =
  Σ(set volume × weight/100) via exercise_muscles; bars colored by tercile of the
  client's own 8-week average (below → warning, within → info, above → success).
- Neglected list under the chart: active muscle groups with zero volume in 14 days.

## Files (exactly these)

- public/progress/index.php · muscle-balance.php
- app/features/progress/queries.php
- app/views/progress/page.php · partials/exercise-charts.php · pr-table.php ·
  balance.php · balance-chart.php

## Query functions

- exercise_progression(PDO, int clientId, int exerciseId, int weeks=12): array
- volume_by_week(PDO, int clientId, ?int templateId, int weeks=12): array
- personal_records(PDO, int clientId, ?int exerciseId, int limit=10): array
- muscle_group_volume(PDO, int clientId, string weekStart): array
- neglected_muscle_groups(PDO, int clientId, int days=14): array

All mirror their MCP-tool namesakes' SQL exactly (single source of truth: keep the SQL in
this slice's queries.php and port verbatim into the record server in Phase 4).

## Action manifest entries

- Screens: progress, muscle-balance. Actions: none (pure views).

## Activity log events

- screen_view only (with query context: exercise/week/client in after_data).

## Status vocabulary

- PR badge → success · balance terciles: below → warning · within → info · above → success.

## Out of scope

- Coach multi-client comparison views, export/download, body-measurement charts
  (measurements slice owns those), Fitbod-style recovery percentages.

## Open Questions

- (none)
