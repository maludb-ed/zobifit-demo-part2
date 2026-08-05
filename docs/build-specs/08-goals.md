# Build spec: goals slice

Exemplar to replicate: admin-catalogs (CRUD) + measurements (chart overlay hooks).
Tenant app. Schema tables: goals (+reads across measurement_entries, logged_sets,
workout_sessions, food_log_entries, nutrition_goals) — never modify.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| goals | /goals/ | Open goals with computed progress; achieved/abandoned history |
| goal-add | /goals/new | Create-goal form (typed) |

Coach: /goals/?client={id}; the coach dashboard's "goals at risk" card links here.

## Goals screen

- Open-goal cards: title (generated: "Reach 82 kg by Oct 1" / "Squat 140 kg × 5" /
  "Train 4×/week" / "Hit calorie goal 6 days/week"), progress bar
  (baseline → current → target, % computed), trajectory note ("on pace" / "behind pace"
  vs. target_date, linear projection), status controls: mark achieved · abandon
  (hx-confirm).
- Progress computation per goal_type (in code, mirrored by MCP list_goals):
  - measurement: latest entry of that type vs. baseline/target
  - strength: best est. 1RM (Epley) for the exercise vs. target_value × target_reps
  - consistency: completed sessions this ISO week vs. target_per_week (4-week bar strip)
  - nutrition: days in last 7 within ±5% of effective calorie goal
- History table: title, outcome badge, closed date.

## Form (goal-add)

- goal_type (radio: measurement / strength / consistency / nutrition) → HTMX-swaps the
  matching fieldset:
  - measurement: measurement_type_id (select) · target_value (number, unit label from
    type) · target_date (date)
  - strength: exercise (typeahead) · target_value (weight, preferred units) · target_reps
    (1–100) · target_date
  - consistency: target_per_week (1–14)
  - nutrition: target_date (evaluates weekly, no other fields)
- client selector (coach only). baseline_value captured server-side at insert (current
  value per the same progress computation).
- Ids: goal-form-field-{name}.

## Files (exactly these)

- public/goals/index.php · form.php · save.php · status.php
- app/features/goals/queries.php · progress.php (the per-type computation functions)
- app/views/goals/page.php · partials/goal-card.php · history-table.php · row.php ·
  form.php · fieldset-{type}.php · saved.php

## Query functions

- find_goals(PDO, int clientId, string status='open'): array (with computed progress attached)
- find_goal(PDO, int id): ?array
- insert_goal(PDO, int clientId, string type, …explicit typed fields, int setBy): array
- update_goal_status(PDO, int id, string status): array
- goal_progress(PDO, array goalRow): array  (dispatches per type; single source of truth
  for the four computations, ported verbatim to the MCP server in Phase 4)

## Action manifest entries

- Screens: goals, goal-add. Actions: goal_create (undo: delete) · goal_update_status
  (undo: restore prior status).

## Activity log events

- screen_view; goal_created (after_data incl. baseline), goal_status_changed
  (before/after).

## Status vocabulary

- open + on pace → success · open + behind pace → warning · achieved → success ·
  abandoned → secondary.

## Out of scope

- Goal editing after creation (abandon + recreate instead — keeps baseline honest),
  notifications/reminders, coach approval flows, multiple simultaneous goals of the same
  measurement type (app enforces one open per type).

## Open Questions

- (none)
