# Build spec: admin-catalogs slice (EXEMPLAR)

Built by the planning-class model; becomes the canonical reference every later slice
replicates. Runs in the **admin app** (`admin.zobifit.com`, `zobifit_control` DB).
Five catalog entities, one repeated CRUD pattern; `muscle-group` is the canonical entity,
the others substitute.

Schema tables: master_muscle_groups, master_equipment, master_exercises,
master_exercise_muscles, master_foods, master_measurement_types — never modify them.

## Screens (×5 entities: muscle-groups, equipment, exercises, foods, measurement-types)

| Screen id | Canonical URL | Purpose |
|---|---|---|
| catalog-{entity}s | /catalog/{entity}s/ | List per canonical table pattern |
| catalog-{entity}-add | /catalog/{entity}s/new | Full-container form (empty) |
| catalog-{entity}-edit | /catalog/{entity}s/{id}/edit | Same form, pre-filled |

No detail views — catalogs edit in place.

## List screens

- **muscle-groups**: name, sort order, active (dot+badge), updated. Sort allowlist: name,
  sort_order, updated_at (default sort_order). Search: name. Page size 25. Row → edit.
- **equipment**: name, active, updated. Same pattern.
- **exercises**: name, equipment (joined name), muscles (top-2 weighted, comma list),
  active, updated. Search: name (ILIKE via trgm). Row → edit.
- **foods**: name, brand, serving (qty+unit), kcal, P/C/F (one column each, 1 decimal),
  active. Search: name, brand. Page size 50. Row → edit.
- **measurement-types**: name, unit, category (badge: body=info, dexa=dark), sort order,
  active. Row → edit.

## Forms

- **muscle-group**: name (text, required) · sort_order (number, default 0) · is_active
  (switch). Ids: catalog-muscle-group-form-field-{name}.
- **equipment**: name (required) · is_active.
- **exercise**: name (required) · equipment_id (select from equipment, blank = bodyweight)
  · instructions (textarea) · is_active · **muscle weightings**: repeating rows
  {muscle_group_id select · weight number 1–100}, add/remove row buttons (inline, no
  modal), at least one row required. Weightings save atomically with the exercise
  (delete-and-reinsert master_exercise_muscles in one transaction).
- **food**: name (required) · brand · serving_qty (number > 0) · serving_unit (text,
  required) · calories_kcal · protein_g · carbs_g · fat_g (numbers ≥ 0, default 0)
  · is_active.
- **measurement-type**: name (required) · unit (required) · category (select body/dexa)
  · sort_order · is_active.

## Files (exactly these, per entity — no additions)

- public/catalog/{entity}s/index.php · form.php · save.php
- app/features/catalog_{entity}s/queries.php
- app/views/catalog_{entity}s/page.php · partials/table.php · row.php · form.php · saved.php

No delete.php anywhere in this slice: catalogs deactivate (is_active=false via the form),
never delete — sync depends on it.

## Query functions (per entity; PDO first, never touch request/response)

- find_{entity}s(PDO, search='', sort='default', page=1): array
- find_{entity}(PDO, int id): ?array
- insert_{entity}(PDO, …explicit fields): array
- update_{entity}(PDO, int id, …explicit fields): array
- exercises additionally: find_exercise_muscles(PDO, int exercise_id): array ·
  replace_exercise_muscles(PDO, int exercise_id, array weightings): void

## Action manifest entries

- Screens: the 15 rows above (see action-manifest.md admin section).
- Actions: catalog_{entity}_create · catalog_{entity}_update — endpoint save.php, undo =
  restore before_data (create-undo deactivates). No confirms (nothing destructive).

## Activity log events

- screen_view on every GET; {entity}_created / {entity}_updated on save (before/after in
  before_data/after_data — the weightings array included for exercises).

## Status vocabulary

- active → success · inactive → secondary. Category badges: body → info, dexa → dark.

## Seed data (ships with this slice, in db/seed/)

- muscle groups (~15: chest, upper back, lats, front/side/rear delts, biceps, triceps,
  forearms, quads, hamstrings, glutes, calves, abs, obliques, lower back)
- equipment (~10: barbell, dumbbell, kettlebell, cable machine, smith machine, leg press,
  other machine, resistance band, bodyweight, cardio machine)
- ~60 common exercises with weightings; ~40 measurement-agnostic starter foods;
  measurement types: weight, waist, hips, chest, arm, thigh, neck (body) + body fat %,
  lean mass, fat mass, visceral fat, bone mineral density (dexa).
- Food catalog seeding at scale (USDA import) stays an open PLAN.md question — the seed
  list here is the floor, not the answer.

## Out of scope for this slice

- Tenant sync job (runs after provisioning exists — slice 2 dependency), CSV import,
  delete endpoints, pagination beyond page-size links, the tenant-side catalog screens.

## Open Questions (must be EMPTY before a worker starts)

- (none)
