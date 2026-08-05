# Build spec: guided-workout slice

Exemplar to replicate: admin-catalogs (patterns) + food-log (inline-add rows).
Tenant app, client-facing. Schema tables: workout_sessions, logged_sets,
program_assignments, program_slots, assignment_substitutions, template_exercises —
never modify.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| workouts | /workouts/ | Start options + session history list |
| workout-session | /workouts/session/{id} | The guided workout |

## Workouts screen

- "Today" section: **one card per active program** with a workout scheduled today
  (concurrent programs are allowed — e.g. muscle building + endurance). Each card:
  program name + focus badge, week/day, template name (substitutions applied), buttons:
  **Start this workout** · **Start from my last {template} session** (copy-previous seed,
  shown when a completed same-type session exists). Below the cards: **Start empty
  workout**. No active programs → the empty-workout button plus a "no program assigned"
  note.
- History table: date, workout (template name or "Ad hoc"), sets, volume (kg), status
  (badge), duration. Row → workout-session (read-only render when completed).
  Sort session_date desc, page size 25.

## Guided workout screen (the payoff)

- Header: template name, program week/day, status, **running session volume vs. previous
  same-type session volume** ("12,480 kg / last time 11,900 kg") — updates on every set
  swap (the progressive-overload readout).
- One card per exercise, in template order (substitutions applied; seeded sessions copy
  the seed's exercises): prescription line (sets × reps @ load), **last time** line (that
  exercise's sets from the previous same-type session), logged-set rows (set #, reps,
  weight in preferred units, RPE optional, delete w/ hx-confirm), inline add-set row
  (defaults: previous set's reps/weight; ids workout-session-ex-{exerciseId}-field-{name}).
  Add-set POST swaps the exercise card + header volume bar (HX-Trigger sessionChanged).
- "Swap exercise" link per card → substitution-picker (session scope: logs different
  exercise without touching the program; picker offers "make permanent").
- Rest timer: plain JS countdown per card using template rest_seconds (starts on set add);
  no persistence, no audio in v1.
- Footer: **Complete workout** (POST → completed_at, status) · Abandon (hx-confirm).
- Ad-hoc sessions: same screen, no prescription/last-time lines, exercise typeahead to add
  cards.

## Files (exactly these)

- public/workouts/index.php · start.php · session.php · log-set.php · delete-set.php ·
  complete.php · abandon.php
- app/features/workouts/queries.php
- app/views/workouts/page.php · partials/today-card.php · history-table.php · row.php ·
  session.php · exercise-card.php · set-row.php · volume-bar.php · saved.php

## Query functions (key signatures)

- resolve_today_workouts(PDO, int clientId, string date): array (one entry per active assignment with a slot that day: program, focus, week/day, template + substitutions)
- start_session(PDO, int clientId, array opts): array  (opts: template/program-day | seed_from_session | empty; seeding copies exercises + prescriptions, NOT sets)
- find_session(PDO, int id): ?array (cards: prescription + last-time sets + logged sets)
- previous_same_type_session(PDO, int clientId, int templateId, ?int beforeSessionId): ?array
- insert_logged_set(PDO, int sessionId, int exerciseId, int setNumber, int reps, ?float weightKg, ?float rpe): array
- delete_logged_set(PDO, int id): bool
- session_volume(PDO, int sessionId): float
- complete_session(PDO, int id): array · abandon_session(PDO, int id): array
- find_sessions(PDO, int clientId, page=1): array

## Action manifest entries

- Screens: workouts, workout-session.
- Actions: workout_start_from_template · workout_start_from_previous (both undo: abandon
  session; both return navigate directive to workout-session) · set_log_create (context
  default: the active session; undo: delete created sets) · workout_complete (undo:
  reopen).

## Activity log events

- screen_view; session_started (after_data: source/seed), set_logged (after_data),
  set_deleted (before_data), session_completed, session_abandoned, exercise_swapped.

## Status vocabulary

- in_progress → warning · completed → success · abandoned → secondary.

## Out of scope

- PR celebration UI (progress slice computes PRs), supersets/drop sets, audio cues,
  offline logging, editing completed sessions (delete/re-log only while in_progress).

## Open Questions

- (none)
