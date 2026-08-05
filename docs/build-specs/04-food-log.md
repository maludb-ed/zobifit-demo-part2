# Build spec: food-log slice

Exemplar to replicate: admin-catalogs; charts per measurements slice.
Tenant app. Schema tables: foods, food_log_entries, nutrition_goals, saved_meals,
saved_meal_items, client_profiles — never modify them.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| food-log | /food-log/?date={d} | The diary: 4 meal sections + day totals vs. goal |
| foods-list | /foods/ | Browse foods (catalog + tenant + own) |
| food-add | /foods/new | Custom food form (prefill: name) |
| saved-meals | /saved-meals/ | List + create saved meals |
| nutrition-goals | /nutrition-goals/ | Current goals (daily grid by weekday + weekly) + history + set form |
| calculators | /calculators/ | BMI · BMR/TDEE · calorie target, prefilled from profile + latest weight |

## Food-log screen (the core)

- Date navigation: prev/next day links + date input (explicit hx-push-url with ?date=).
- Four meal sections (breakfast/lunch/dinner/snack), each: entries table (food, servings,
  kcal, P/C/F, delete w/ hx-confirm) + inline add row: food search (select2-style
  typeahead over trgm query, id food-log-search-{meal}) · servings (number, default 1) ·
  add button (HTMX POST, swaps the meal section).
- Recent/frequent strip above search: last 10 distinct foods for that meal → one-tap add.
- Day summary bar: consumed / goal / remaining for kcal + P/C/F (uses the effective goal
  for that date: weekday override else daily default); weekly average vs. weekly goal line
  below (current ISO week).
- "Log a saved meal" select per section.

## Forms

- **Custom food** (food-add): name (required) · brand · serving_qty (number>0, required) ·
  serving_unit (required) · calories_kcal · protein_g · carbs_g · fat_g (≥0, default 0).
  provenance: coach → tenant, client → client (set server-side, never a form field).
- **Nutrition goal set** (on nutrition-goals): period (radio daily/weekly) · day_of_week
  (select, only for daily; blank = all days) · calories_kcal · protein_g · carbs_g · fat_g
  (all optional, ≥1 required) · effective_from (date, default today) · client selector
  (coach only). Inserts a new row — never updates (supersede semantics).
- **Saved meal**: name + item rows {food search, servings}, add/remove inline.

## Calculators screen

- Three cards (BMI, BMR/TDEE, calorie target), each: inputs prefilled from
  profile + latest weight entry, Calculate button (HTMX POST → result partial), and on the
  calorie-target card a "Set as my calorie goal" button that POSTs nutrition-goals/save.php
  with the computed value (undo per goal-set semantics).
- Math lives in app/features/calculators/formulas.php: Mifflin-St Jeor; Katch-McArdle when
  a body-fat % entry exists; activity multipliers 1.2/1.375/1.55/1.725/1.9; 1 kg body
  mass ≈ 7700 kcal for rate conversion. PHP mirrors the MCP tools' math exactly.

## Files (exactly these)

- public/food-log/index.php · save.php · delete.php · log-saved-meal.php
- public/foods/index.php · form.php · save.php
- public/saved-meals/index.php · form.php · save.php · delete.php
- public/nutrition-goals/index.php · save.php
- public/calculators/index.php · calculate.php
- app/features/food_log/queries.php · foods/queries.php · saved_meals/queries.php ·
  nutrition_goals/queries.php · calculators/formulas.php
- app/views/{feature}/… per the canonical partial set (page/table/row/form/saved per feature;
  food-log adds partials/meal-section.php · day-summary.php)

## Query functions (key signatures)

- find_food_log_day(PDO, int clientId, string date): array (entries grouped by meal + totals)
- find_recent_foods(PDO, int clientId, string meal, int limit=10): array
- search_foods(PDO, string q, int clientId, int limit=20): array (catalog + tenant + own client rows)
- insert_food_log_entry(PDO, int clientId, int foodId, string date, string meal, float servings): array
- delete_food_log_entry(PDO, int id): bool
- effective_nutrition_goal(PDO, int clientId, string date): ?array (weekday override → daily default)
- current_weekly_goal(PDO, int clientId, string date): ?array
- insert_nutrition_goal(PDO, int clientId, …explicit fields, int setBy): array
- find_nutrition_goal_history(PDO, int clientId, page=1): array
- log_saved_meal(PDO, int clientId, int savedMealId, string date, string meal): array

## Action manifest entries

- Screens: the 6 above. Actions: food_log_entry_create · food_log_entry_delete (**confirm**)
  · saved_meal_log · custom_food_create · nutrition_goal_set (undo: re-insert prior goal).

## Activity log events

- screen_view; food_log_created/deleted, food_created, saved_meal_created/logged,
  nutrition_goal_set (before_data = superseded goal, after_data = new).

## Status vocabulary

- Day summary: under goal → success · within 5% over → warning · >5% over → danger
  (calories); macros show plain numbers, no badges.

## Out of scope

- Barcode/photo scanning (PLAN §9.2), recipe builder with computed nutrition (§9.3),
  micronutrients, water tracking, copy-day.

## Open Questions

- (none)
