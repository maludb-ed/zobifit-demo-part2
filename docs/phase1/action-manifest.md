# Zobifit action manifest (Phase 1)

Per the chat-actions skill: the routing table for the unified assistant. The `navigate`
tool's screen list and validation are **generated from this manifest** at actions-server
startup; every action tool below calls the app's own PHP endpoint with a signed action
token. A screen or action missing here is unreachable by voice — unfinished design.

Policy (locked): creates/updates execute immediately + Undo; destructive actions confirm
first; ambiguity asks one short question. Undo definitions below are what `undo_last`
executes, resolved via the activity log's before/after data.

Auth pages (login, 2FA, reset) are full-page navigations outside the session — no assistant
surface, so they are deliberately absent.

## Screen registry — tenant app (`{slug}.zobifit.com`)

| Screen id | Canonical URL | When the user wants… |
|---|---|---|
| dashboard | / | their overview (coach: client status board; client: today's workout, macros left, goals) |
| clients-list | /clients/ | the client roster |
| client-view | /clients/{id} | one client's full picture |
| client-add | /clients/new | to invite a new client |
| client-edit | /clients/{id}/edit | to edit a client's profile, equipment, or coach |
| exercises-list | /exercises/ | to browse or search exercises |
| exercise-add | /exercises/new | to create a custom exercise (prefill: name) |
| exercise-edit | /exercises/{id}/edit | to edit an exercise or its muscle weightings |
| templates-list | /templates/ | to see workout templates |
| template-add | /templates/new | to build a new workout template |
| template-edit | /templates/{id}/edit | to change a template's exercises/prescriptions |
| programs-list | /programs/ | to see training programs |
| program-add | /programs/new | to create a program (duration × days/week grid) |
| program-edit | /programs/{id}/edit | to fill or change a program's schedule grid |
| program-assign | /programs/assign?client={id} | to put a client on a program |
| substitution-picker | /programs/substitute?assignment={id}&exercise={id} | alternatives for an exercise they can't do |
| food-log | /food-log/?date={d} | the food diary ("go to the food log page") |
| foods-list | /foods/ | to browse foods |
| food-add | /foods/new | to add a custom food (prefill: name) |
| saved-meals | /saved-meals/ | their saved meals |
| nutrition-goals | /nutrition-goals/ | to view or change calorie/macro goals |
| workouts | /workouts/ | the workouts page: history + start a workout |
| workout-session | /workouts/session/{id} | the guided workout in progress |
| progress | /progress/ | charts: exercise progression, volume trends ("go to the progress page") |
| muscle-balance | /progress/muscle-balance | weekly volume per muscle group |
| measurements | /measurements/ | body measurements + DEXA history and graphs |
| measurement-add | /measurements/new | to record a measurement or DEXA results |
| goals | /goals/ | their goals and progress |
| goal-add | /goals/new | to set a new goal |
| calculators | /calculators/ | BMI / BMR / TDEE / calorie-target calculators |
| ama | /ama/ | a full conversation with the assistant |
| settings | /settings/ | profile, units, 2FA |
| settings-mcp | /settings/mcp | their MCP endpoints + access tokens (SaaS Plus+) |

## Screen registry — admin app (`admin.zobifit.com`)

| Screen id | Canonical URL | When the admin wants… |
|---|---|---|
| admin-dashboard | / | platform health: tenants, usage |
| tenants-list / tenant-add / tenant-view | /tenants/ … | manage tenants |
| catalog-muscle-groups | /catalog/muscle-groups/ | the muscle-group taxonomy |
| catalog-equipment | /catalog/equipment/ | the equipment list |
| catalog-exercises | /catalog/exercises/ | the master exercise library + weightings |
| catalog-foods | /catalog/foods/ | the master food database |
| catalog-measurement-types | /catalog/measurement-types/ | standard + DEXA measurement types |

(Each catalog screen has sibling `-add`/`-edit` pages following the same URL pattern.)

## Action registry — tenant app

| Action | Endpoint (POST) | Params (slot-filled) | Undo | Confirm? |
|---|---|---|---|---|
| `food_log_entry_create` | /food-log/save.php | food (name→resolved), servings, meal, date (default today) | delete the entry | no |
| `food_log_entry_delete` | /food-log/delete.php | entry (resolved from context/description) | — | **yes** |
| `saved_meal_log` | /food-log/log-saved-meal.php | saved_meal (name→resolved), meal, date | delete created entries | no |
| `custom_food_create` | /foods/save.php | name, serving, calories, protein_g, carbs_g, fat_g | delete if unreferenced | no |
| `nutrition_goal_set` | /nutrition-goals/save.php | calories?, protein_g?, carbs_g?, fat_g?, period (default daily), day_of_week?, client (coach only) | re-insert prior goal (supersede-restore) | no |
| `measurement_entry_create` | /measurements/save.php | type (name→resolved), value, unit-hint, date, source | delete the entry | no |
| `goal_create` | /goals/save.php | goal_type, target (typed per §3), target_date? | delete the goal | no |
| `goal_update_status` | /goals/status.php | goal, status achieved/abandoned | restore prior status | no |
| `workout_start_from_template` | /workouts/start.php | template or program-day ref ("Day 2 of full body") | abandon the empty session | no · navigates to workout-session |
| `workout_start_from_previous` | /workouts/start.php | type ref ("my last leg day") — seeds from previous same-type session | abandon the session | no · navigates to workout-session |
| `set_log_create` | /workouts/log-set.php | exercise (context default: current session), sets (default 1), reps, weight_lbs/kg | delete created set rows | no |
| `workout_complete` | /workouts/complete.php | session (context default) | reopen session | no |
| `program_substitute_exercise` | /programs/substitute.php | from_exercise, to_exercise, scope session/program | remove substitution | no |
| `program_assign` | /programs/assign.php | client, program, start_date (coach only; concurrent programs allowed) | remove the new assignment | no |
| `program_end` | /programs/end.php | client, program (name/focus → resolved active assignment) | restore assignment to active | no |
| `client_invite` | /clients/invite.php | email, name, role (coach only) | revoke invitation | no |
| `client_archive` | /clients/archive.php | client (coach only) | — | **yes** |
| `exercise_create` | /exercises/save.php | name, equipment?, muscle weightings | deactivate if unreferenced | no |
| `undo_last` | (meta) | — | inverse of the last action from conversation + activity log | no |

Admin actions mirror the catalog screens (`catalog_{entity}_create/update`,
`tenant_create`, `tenant_suspend` — suspend confirms) and follow identical semantics.

## Slot-filling rules (bind Phase 4 implementation)

- Unit-bearing field names (`weight_lbs`), conversion in the description, plausibility
  bounds on every numeric field (per chat-actions tool-authoring).
- Names in, ids resolved server-side; ambiguity fails with candidates, never guesses.
- Screen context (`data-screen`, `data-entity`, `data-record-id` via hx-vals) supplies
  defaults — "log 12 reps at 225" inside a session needs no exercise param when the screen
  shows one.
- Every action POSTs with the short-lived HMAC action token; the PHP endpoint enforces the
  same validation, authorization, and activity logging as a human submission.
