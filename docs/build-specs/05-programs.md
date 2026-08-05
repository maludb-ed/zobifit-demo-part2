# Build spec: programs slice (templates & programs)

Exemplar to replicate: admin-catalogs. Tenant app, coach-facing (plus the
substitution-picker, client-reachable).
Schema tables: exercises, exercise_muscles, workout_templates, template_exercises,
programs, program_slots, program_assignments, assignment_substitutions — never modify.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| exercises-list | /exercises/ | Tenant exercise browser (catalog + custom) |
| exercise-add / exercise-edit | /exercises/new · /{id}/edit | Custom exercise + weightings (form copied from admin catalog exercise form; catalog-provenance rows read-only) |
| templates-list | /templates/ | Workout templates |
| template-add / template-edit | /templates/new · /{id}/edit | Template builder |
| programs-list | /programs/ | Programs |
| program-add / program-edit | /programs/new · /{id}/edit | Structure + schedule grid |
| program-assign | /programs/assign?client={id} | Assign program to client |
| substitution-picker | /programs/substitute?assignment={id}&exercise={id} | Ranked same-muscle alternatives |

## Template builder (template-add/edit)

- Header fields: name (required) · description.
- Exercise rows (ordered, move up/down controls, add/remove inline): exercise (typeahead)
  · target_sets (1–20) · target_reps_low · target_reps_high (blank = fixed) · load mode
  (select none/kg/%1RM/RPE → shows matching input) · rest_seconds · note.
  Ids: template-form-row-{n}-field-{name}.

## Program editor (program-add/edit)

- Fields: name · focus (text with datalist suggestions: muscle building, endurance,
  mobility, general fitness) · description · weeks (1–104) · days_per_week (1–7) ·
  repeats_weekly (switch, default on).
- Grid: repeats_weekly on → one row of day 1..N template selects; off → weeks × days grid
  (each cell a template select; blank cell = rest/unfilled, week rows can copy the
  pattern row). Grid swaps via HTMX when structure fields change; unsaved grid state is
  serialized in the form (no JS state store).
- Muscle-emphasis summary strip under the grid: computed volume share per muscle group
  across the grid's templates (server-rendered, read-only).

## Assignment (program-assign)

- Current active programs table for the selected client (program, focus, week, started),
  each row with an "End program" control (status → completed, hx-confirm not required —
  undoable action).
- Assign form: client (select) · program (select, shows "name — focus") · start_date
  (date, default next Monday). **Concurrent programs are allowed**; only a duplicate
  active assignment of the same program is rejected (unique index; surface the DB error
  as a friendly validation message).

## Substitution picker

- Context: assignment + from-exercise. Table of candidates ranked by weighting-vector
  cosine similarity, filtered to the client's equipment (client_equipment; bodyweight
  always allowed) — computed in SQL, mirroring the MCP suggest_exercise_substitutes tool.
- Columns: exercise, equipment, similarity %, shared top muscles. Row action "Use for this
  program" → POST substitute.php (assignment_substitutions upsert).

## Files (exactly these)

- public/exercises/index.php · form.php · save.php
- public/templates/index.php · form.php · save.php
- public/programs/index.php · form.php · save.php · assign.php · end.php · substitute.php
- app/features/{exercises,templates,programs}/queries.php
- app/views/{exercises,templates,programs}/… canonical partial sets; programs adds
  partials/grid.php · emphasis.php · substitutes-table.php

## Query functions (key signatures)

- search_exercises(PDO, string q, ?int clientId, int limit=20): array
- insert_exercise(PDO, …fields, string provenance, ?int ownerUserId): array + replace_exercise_muscles(...)
- find_templates(PDO, search, sort, page), find_template(PDO, id) (with exercise rows)
- save_template(PDO, ?int id, array header, array exerciseRows): array (transactional)
- find_programs / find_program (with slots), save_program(PDO, ?int id, array header, array slots): array
- find_active_assignments(PDO, int clientId): array
- assign_program(PDO, int clientId, int programId, string startDate, int assignedBy): array
- end_assignment(PDO, int assignmentId): array
- find_substitute_candidates(PDO, int exerciseId, int clientId, int limit=10): array
- upsert_substitution(PDO, int assignmentId, int fromExerciseId, int toExerciseId, ?string reason, int createdBy): array

## Action manifest entries

- Screens: the 9 above. Actions: exercise_create · program_assign (undo: remove the new
  assignment) · program_end (undo: restore to active) · program_substitute_exercise
  (undo: remove substitution).

## Activity log events

- screen_view; exercise_created/updated, template_created/updated (after_data = full row
  set), program_created/updated, program_assigned (before_data = prior assignment),
  substitution_created.

## Status vocabulary

- program assignment: active → success · completed → secondary · abandoned → danger.

## Out of scope

- Diet program templates (PLAN §9.9), template/program deletion (deactivate only),
  supersets/drop-set structures (§9.4), program sharing between tenants.

## Open Questions

- (none)
