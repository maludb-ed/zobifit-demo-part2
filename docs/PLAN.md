# Zobifit — Memory-First Plan

Zobifit is a fitness and diet coaching platform. A **coaching business is the client** (B2B2C):
each coach or training business is a tenant with its own dedicated PostgreSQL database and its
own MCP endpoints (SaaS Plus+ — sovereign data). The coach's athletes are users inside that
tenant. Coaches prescribe training programs and nutrition goals; clients log workouts and
food; both sides ask the system how it's going. Every screen carries a voice-first chat
command bar: users dictate what they want recorded or where they want to go, and the
assistant navigates and does the data entry on their behalf.

Draft v2 — adds role requirements, MyFitnessPal-class food logging, guided workout templates,
muscle-group scoring, goal setting, and the voice/chat command bar (researched against
MyFitnessPal, Hevy/Strong, and Fitbod — see §10). The memory model is written in domain language; translating it to SQL is a
build task that happens only after this document converges.

---

## 1. Actors & roles

Three login roles, each with its own dashboard. Authentication is a standard login page with
session auth (php-session-auth skill); users are invited, never self-registered, in v1.

| Actor | Role | Dashboard answers | Scope |
|---|---|---|---|
| **Admin** | Platform administrator (us) | "Is the platform healthy? Which tenants are active? Are the master catalogs complete?" | Control plane: tenants, master catalogs (muscle groups, exercise library, food database). Never inside tenant client data. |
| **Coach** | Tenant owner or staff trainer | "How are my clients doing right now?" — adherence, missing logs, programs ending, goals at risk | Their tenant: clients, programs, foods, goals |
| **Client** | A coached athlete | "What's my workout today, what's left to eat, am I on track for my goals?" | Their own data |
| **Unified assistant** | System actor — one Agent SDK service behind both the AMA page and the command bar | — | Reads via the two read-only MCP servers; acts via the actions MCP server (§6) |
| **Client's own AI tools** | Tenant-owned agents (Claude Desktop etc.) | — | Authenticated MCP access to *their* databases |

## 2. Question inventory

Every feature must trace to a question here; every question must trace to memory that answers
it. **R** = record question (PostgreSQL), **A** = activity question (MaluDB via logs).

### Coach asks

- R — Which of my clients haven't logged food (or a workout) in the last N days?
- R — Is Maria hitting her calorie/protein goals this week (daily and weekly average)?
- R — What did Jake actually lift last session vs. what the template prescribed?
- R — Which exercises hit the same muscles as barbell rows using only dumbbells? *(program
  building and substitution both lean on the weighting system)*
- R — How is Jake's squat progressing over the last 8 weeks?
- R — Is Jake's session volume trending up week over week (is progressive overload working)?
- R — Which muscle groups has Jake trained this week, and which are neglected?
- R — Which clients' programs end within 2 weeks (who needs new programming)?
- R — What are Maria's current macro goals, and what were they before?
- R — Which clients are on track / off track for their goals?
- R — Which clients improved body composition since their last DEXA scan?
- A — When did I last adjust Jake's program, and what did I change?
- A — When did Maria stop logging regularly?

### Client asks

- R — What's my workout today? What week and day of my program am I on?
- R — I don't have a cable machine — what can I do instead that hits the same muscles?
- R — What did I lift for this exercise last time?
- R — What did I do in my previous workout of this type (my last leg day on this program)?
- R — Was that session more volume than the one before? Is my volume trending up for this
  workout type?
- R — How many calories / how much protein do I have left today?
- R — How does this week's average compare to my weekly goal?
- R — What's my BMI? What's my BMR? How many calories a day do I burn? *(computed from
  profile + latest measurements — no new memory needed)*
- R — What's the maximum I can eat per day and still lose 1 lb a week? *(computed: TDEE from
  profile + latest weight, minus the deficit for the target rate)*
- R — What did I eat yesterday? Last Tuesday?
- R — What's my best squat set ever? Any new PRs this month?
- R — When was the last time I worked out legs? *(logged sets × muscle weightings — the weighting system answers time questions too)*
- R — What templates are available for a full-body workout?
- R — When is the last time I did a bench press?
- R — What is my max weight ever lifted on bench press?
- R — Which muscle groups did I hit this week? What's my training balance?
- R — What's my weight / waist / body-fat trend over time?
- R — What did my last DEXA show, and how does it compare to the scan before it?
- R — Am I on track for each of my goals (any measurement, strength, consistency)?
- A — When did my coach last update my plan?

### Admin asks

- R — How many tenants, coaches, and clients are on the platform?
- R — Are the master catalogs complete (muscle groups, exercises, foods)?
- A — Is tenant X actually using the product (logins, logging volume)?

The long tail neither list anticipates is the AMA agent's job — same memories, no new screens.

## 3. Record memory (PostgreSQL 17)

Entities in domain language. Current truth, edited in place.

### Master catalogs (control-plane, admin-managed; seeded into each tenant database)

- **Muscle group** — the admin-defined taxonomy (chest, lats, quads, hamstrings…). The axis
  for all muscle scoring.
- **Equipment (catalog)** — barbell, dumbbell, cable machine, kettlebell, bodyweight… the
  admin-managed list exercises reference and clients declare access to.
- **Exercise (catalog)** — a named movement with **required equipment** (from the equipment
  catalog), instructions, and **muscle weightings**: each exercise carries weights across
  muscle groups (primary/secondary shorthand in the UI, a 0–100 weight underneath — the
  Fitbod model). Scoring *and substitution* depend on these: "an exercise that hits the same
  muscles" is a similarity computation over weighting vectors, filtered by equipment.
- **Food (catalog)** — the preloaded food database: name, brand, serving size + unit, calories,
  protein, carbs, fat (micronutrients later). Seeding source is an open question (§9).
- **Measurement type (catalog)** — the standard body measurements and the DEXA measurement
  set, with units (detail under *Measurements & goals* below).

*Architecture note:* catalogs live in the control plane and are copied into tenant databases at
provisioning; catalog updates propagate by a sync job. Tenant- and client-created entries live
only in the tenant database, marked by provenance (catalog / tenant / client).

### People & access (per tenant)

- **Coach** — staff user; role owner or staff.
- **Client** — a coached person; status (active/paused/archived), assigned coach, invited by
  coach. Carries a **profile**: birthdate, sex, height, activity level — required by the
  energy calculators ("max calories to lose 1 lb/week" is unanswerable without them; current
  weight comes from measurement entries) — and **available equipment** (which catalog
  equipment they can actually use), which drives exercise substitution.

### Diet (per tenant)

- **Custom food** — any role can add foods they eat (same shape as catalog foods, tenant or
  client provenance).
- **Food log entry** — *Client ate a portion of a Food at a time on a date, under a meal
  (breakfast / lunch / dinner / snack).* The atomic diet fact. Recent/frequent foods for
  quick logging are queries over these, not new memory.
- **Nutrition goal** — calories + macros in grams (protein, carbs, fat), set by the coach
  *or by the client themselves* (self-service confirmed; coaches see changes via goal history
  and the activity log), with an effective-from date. Two granularities:
  - **Daily goals**, with optional per-day-of-week overrides (e.g. higher carbs on training
    days — the MyFitnessPal Premium pattern);
  - **Weekly goals** — weekly totals/averages, so one heavy day doesn't read as failure.
  Goals are superseded, never overwritten — "what were her goals in March?" stays answerable.

### Training (per tenant)

- **Custom exercise** — any role (admin via catalog, coach, or client) can add exercises, and
  whoever adds one sets its muscle weightings.
- **Workout template** — a named workout: ordered exercises, each with prescribed sets × reps
  and a load prescription (weight, %-of-max, or RPE). **The critical flow: starting a workout
  loads its template, which guides the client exercise-by-exercise through the session**, showing
  last time's numbers per exercise (the Hevy/Strong pattern).
- **Program** — the coach's reusable training product, with an explicit structure: a name,
  a **focus** (muscle building, endurance, mobility… — free text, since coaches name their
  own methodologies), a **duration in weeks** and **training days per week** (e.g.
  *full-body, 5 days/week, 12 weeks*), and a **schedule grid**: each week × day slot holds
  a target workout template. A program may repeat one weekly pattern or vary templates week
  by week (how deloads and progression blocks are expressed).
- **Program assignment** — *Client is on Program starting date X.* History kept. **A client
  may run several programs concurrently** (e.g. a muscle-building program alongside an
  endurance program); each assignment ends independently, and the focus label keeps them
  distinguishable ("start today's endurance workout"). "What's my workout today?" can
  therefore answer with more than one workout. The assignment also carries the client's
  **exercise substitutions** (below), so "week 3, day 2" always resolves to a concrete,
  personalized workout.
- **Exercise substitution** — *within Client C's assignment of Program P, exercise X is
  replaced by exercise Y* (typically because C lacks X's equipment). The coach's master
  program is never modified; the swap lives on the assignment and applies to every future
  session that template generates. Candidate substitutes are **computed, not curated**:
  exercises ranked by muscle-weighting similarity to X, filtered by the client's available
  equipment. A one-off in-session swap is just logging a different exercise, with an offer to
  make it permanent.
- **Workout session** — *Client performed a workout on a date*: source template (or ad-hoc),
  completion status, notes. The source template gives every session a **type** — "previous
  workout of the same type" (last leg day on this program) is a query, not new memory.
  A new session can be **seeded by copying the previous same-type session**: its exercises
  and sets prefill as the starting base, and the client adjusts from there instead of
  rebuilding the workout — the progressive-overload workflow.
- **Logged set** — the atomic training fact: exercise, weight, reps, optional RPE, within a
  session. Progress, PRs, and muscle scores are all queries over logged sets.
- **Session volume** — *computed, not stored*: total volume (sets × reps × weight, overall
  and per muscle group) for a session. **Progressive overload is a comparison**: today's
  running volume vs. the previous same-type session, surfaced live in the guided workout so
  the client can see they're doing a little more than last time.
- **Muscle-group score** — *computed, not stored*: training volume per muscle group per period
  (session / week), derived from logged sets × exercise muscle weightings. Powers the balance
  view for clients and coaches.

### Measurements & goals (per tenant)

- **Measurement type** — the catalog of things a body can measure: standard tape-and-scale
  types (weight, waist, hips, chest, arm, thigh…) and **DEXA-scan types** (body-fat %, lean
  mass, fat mass, visceral adipose tissue, bone mineral density, regional lean/fat…). Each
  has a unit and a category. Admin manages the standard set in the master catalog;
  tenants and clients can add custom types (same provenance pattern as foods and exercises).
- **Measurement entry** — *Client measured type T = value on date D, from source S* (manual
  or DEXA scan). A DEXA visit is simply a batch of entries sharing a date and source.
  Trend graphs over time are queries over entries — no extra memory needed.
- **Goal** — *Client is trying to reach X by date Y*: a target for **any measurement type**
  (weight 180 lbs, body fat 15 %…), a strength target (exercise + weight × reps), a
  consistency target (workouts/week), or a nutrition-adherence target. Set by coach or
  client; status open/achieved/abandoned. Progress is computed from logged facts — never
  self-reported.

### Traceability check (memory → question)

Every entity above answers questions in §2. Run the loop the other way each revision: a new
question that can't be answered = a gap here.

## 4. Activity memory (MaluDB — logging from day one)

Activity questions (§2, marked A) are answered from the application log stream, never from
screens. Logging is not optional and cannot be backfilled. Every request/action logs:

- **when** — timestamp
- **who** — actor id + role (admin / coach / client / agent) + tenant
- **what** — action (viewed, created, updated, deleted, logged-in, assigned…)
- **where** — screen/route
- **which** — affected record ids
- **before/after** — values on any record change (goal edits, program changes, set corrections)

Actions performed *by the assistant on a user's behalf* (§6) log identically — they go through
the same PHP endpoints — and the command bar's **Undo** resolves the inverse action from this
log, so voice data entry depends on the logging discipline too.

Emitted to a structured log; an ingestion job ships it into MaluDB continuously. The activity
questions in §2 are the acceptance test for log coverage.

## 5. Shipped MCP servers (per tenant, read-only)

Tool surfaces derived from §2, designed alongside the schema (mcp-servers skill).

**Record-memory server (PostgreSQL):** `list_clients`, `get_client_overview`,
`get_nutrition_log`, `get_nutrition_adherence` (daily + weekly vs. goals),
`get_current_program`, `get_program_schedule` (the week × day grid with substitutions
applied — "what's week 3 day 2?"), `suggest_exercise_substitutes` (rank by muscle-weighting
similarity, filter by the client's equipment — a compute tool, read-only),
`list_workout_templates` (filter by muscle-group emphasis —
"what full-body templates are there?"), `get_workout_history` (filter by exercise *or*
muscle group — "when did I last do bench press / train legs?"), `get_previous_workout`
(the last session of a given type with all its sets — powers both "what did I do last leg
day?" and the copy-as-starting-base flow), `get_volume_trend` (session volume over time for
a workout type or muscle group — the progressive-overload check), `get_personal_records`
("my max bench ever"), `get_exercise_progression`,
`get_muscle_group_scores`, `get_measurement_history`, `list_goals`, `get_goal_progress`,
the **calculator suite** (deterministic body/energy math in code, never LLM arithmetic;
compute tools, still read-only): `calculate_bmi` (latest weight + profile height),
`calculate_bmr_tdee` (BMR via Mifflin-St Jeor from profile + latest weight — upgraded to
Katch-McArdle lean-mass math when a body-fat % measurement exists, e.g. from DEXA; TDEE =
BMR × activity level — "how many calories a day do I burn?"), and `calculate_calorie_target`
(TDEE minus the deficit for a requested loss/gain rate),
`list_clients_missing_logs`, `list_programs_ending` — plus one guarded read-only search tool
for the long tail.

**Activity-memory server (MaluDB):** `get_actor_timeline`, `get_record_history`
(before/after trail), `search_activity`.

Consumers: the unified assistant, and the tenant's own AI tools via authenticated endpoints.
A third, **localhost-only actions MCP server** (§6) performs writes by calling the app's own
PHP endpoints — it belongs to the assistant and is never exposed to clients; the two read
servers stay strictly read-only.

## 6. Voice & chat command bar (chat-actions)

Every screen ships a fixed bottom command bar — voice-first, since users dictate into it
(Wispr Flow-style). The assistant interprets the utterance **plus the context of the screen
they're on** and either navigates, enters data on the user's behalf, or answers a question.
Architecture per the plugin's chat-actions skill:

- **One unified assistant** — the same Claude Agent SDK service as the AMA page, with three
  tool groups: the two read-only MCP servers, a `navigate` tool resolved against a screen
  registry, and action tools on the actions MCP server.
- **Actions go through the app's own PHP controllers** — same validation, same authorization,
  same activity logging as a human click, authorized per-request by a short-lived signed
  action token. Nothing writes around the PHP layer.
- **Confirmation policy (locked):** creates/updates execute immediately and reply with the
  interpretation + an **Undo** control ("Logged 3 × bench @ 45 lbs"); destructive actions
  always confirm first; ambiguity asks one short question instead of guessing.
- **Plain HTMX transport:** navigation returns `HX-Location`, data actions fire `HX-Trigger`
  refresh events, long AMA answers stream over SSE from the assistant service. No SPA.

The requested utterances, routed:

| Utterance | Resolves to |
|---|---|
| "Go to the food log page" | `navigate("food-log")` |
| "Go to the workouts page" | `navigate("workouts")` |
| "Go to the progress page" | `navigate("progress")` |
| "Begin a new workout using Day 2 of the full body template" | `workout_start_from_template(...)` — resolves the template, creates the session, then a navigate directive into the guided workout |
| "What did I do last leg day?" | `get_previous_workout(...)` — read, answered in the reply |
| "Copy my last leg day as today's workout" | `workout_start_from_previous(...)` — seeds a new session from the previous same-type session's sets, then navigates into the guided workout + Undo |
| "Log two eggs for breakfast" | `food_log_entry_create(...)` + Undo |
| "Record three sets of bench press at 45 pounds" | `set_log_create(...)` against the active session + Undo |
| "My weight this morning was 181 pounds" | `measurement_entry_create(...)` + Undo |
| "Set my daily protein goal to 150 grams" | `nutrition_goal_set(protein_g=150)` + Undo (a goal supersedes the old one; undo restores it) |
| "Set my maximum calorie goal to 2,500 calories" | `nutrition_goal_set(calories=2500)` + Undo |
| "What's my BMI?" / "How many calories do I burn a day?" | `calculate_bmi` / `calculate_bmr_tdee` — read/compute, answered in the reply |
| "Calculate the max calories I can eat to lose 1 lb a week" | `calculate_calorie_target(rate="1 lb/week")` — a read/compute call, no write; the reply gives the number and offers to set it as the goal |
| "I don't have a cable machine — what can I do instead?" | `suggest_exercise_substitutes(...)` using screen context for which exercise "instead" means |
| "Swap cable rows for dumbbell rows for the rest of my program" | `program_substitute_exercise(...)` on the assignment + Undo |

Compound utterances chain naturally: *"…and make that my calorie goal"* is the compute call
followed by `nutrition_goal_set` in the same turn — the act+undo policy still applies to the
write at the end.

**The action manifest is a Phase-1 design artifact**, the sibling of the schema and MCP tool
surface: a **screen registry** (stable id, canonical URL, "when the user wants…" description)
and an **action registry** (name, endpoint, parameter schema, undo definition, confirm flag).
Every screen in §7 gets a registry entry; every mutating action gets an action entry. A screen
or action missing from the manifest is unreachable by voice — unfinished design.

## 7. Features = views over memory

| Screen | Role | The question it pre-packages |
|---|---|---|
| Login | all | entry; role decides which dashboard |
| Admin dashboard | admin | platform health, tenant/usage counts |
| Catalog management | admin | muscle groups, exercise library + weightings, food database |
| Coach dashboard | coach | client adherence, missing logs, programs ending, goals at risk |
| Client roster / detail | coach | who am I coaching; one client's full picture |
| Program builder | coach | exercises → templates → programs: set duration × days/week, fill the week × day grid with target workouts |
| Substitution picker | client/coach | "no equipment for this" → ranked same-muscle alternatives; swap for the session or the program |
| Assign program / set goals | coach | prescriptions with effective dates |
| Client dashboard | client | today's workout, calories/macros left, goal progress |
| Measurements | client/coach | log tape/scale entries, enter DEXA results; per-measurement graphs vs. goal |
| Food logging | client | diary by meal; search catalog + custom foods; recent/frequent quick-add |
| **Guided workout** | client | template loads at start — or seeded as a copy of the previous same-type session; exercise-by-exercise with prescription + last time's numbers; running volume vs. previous session (progressive overload); log sets as you go |
| History & progress | client/coach | per-exercise charts, PRs, diary history, measurement trends, volume trend per workout type |
| Muscle balance | client/coach | weekly volume per muscle group from the weighting system |
| Calculators | client/coach | BMI, BMR, daily calories burned (TDEE), calorie target for a loss/gain rate — prefilled from profile + latest measurements, with one-tap "set as goal" |
| Goals | client/coach | targets and computed progress |
| Command bar | all | on every screen: "take me there" / "record this for me" — navigation and voice data entry (§6) |
| AMA page | all | the long tail, via the assistant |

**Charting standard: Chart.js** (bundled locally, no CDN). Every graph in the app uses it —
measurement trends vs. goal, per-exercise progression, session volume trends, muscle-balance
views, and the dashboards' summary charts. Because screens load as HTMX partials, chart
initialization hooks the HTMX swap lifecycle (`htmx:afterSettle` on `#page-content`) rather
than page load, and instances are destroyed before their partial is swapped out — a Phase 2
shell concern so every slice inherits it.

## 7a. Phase 3 slice order (proposed — dependency-driven)

Auth + shell are Phase 2, not slices. Slices build one at a time, end-to-end, each done only
when its screens work at 375 px, log activity, and are registered in the action manifest.

0. **Environment & connectivity gate** — before *any* application building (before the
   Phase 1 schema is applied, and long before Phase 2 PHP): verify the target environment
   end-to-end on the Ubuntu 24.04 host —
   - PostgreSQL 17 installed, running, and accepting connections; control-plane and a first
     tenant database can be created;
   - PHP with `pdo_pgsql` connects and round-trips a query (a checked-in smoke-test script,
     e.g. `scripts/verify-db.php`, that prints `SELECT version()` and a test write/read/delete
     on a scratch table);
   - MaluDB extensions install cleanly and the log-ingestion path is writable;
   - Apache serves PHP.
   Nothing else starts until this gate passes — a connectivity problem found here costs
   minutes; found mid-Phase-2 it poisons every diagnosis that follows.

1. **Admin catalogs** — muscle groups, equipment, exercise library + weightings, measurement
   types, food database. First because nearly everything references a catalog.
2. **Clients & profiles** — coach invites clients; roster; profile (birthdate, sex, height,
   activity level, available equipment). Everything client-scoped depends on it.
3. **Measurements & DEXA** — entry + graphs. Small, self-contained, high demo value early.
4. **Food logging & nutrition goals** — diary by meal, custom foods, daily/weekly goals,
   calorie calculator.
5. **Templates & programs** — workout templates, the week × day program grid, assignment,
   substitutions.
6. **Guided workout** — start-from-template flow, logged sets, PRs. The exemplar's payoff;
   depends on 5.
7. **Progress & muscle balance** — per-exercise charts, muscle-group scores. Pure views over
   memory logged by 4/6.
8. **Goal setting & tracking** — attaches to measurements, sets, and nutrition; last because
   it reads everything.

Slice 1 is the exemplar the planning-class model builds; later slices are worker-built
replications per their Phase 1 build specs.

## 8. Stack & deployment

Per the plugin's tech stack — complexity is earned, not assumed:

- **Ubuntu 24.04 LTS** (corrected from "20.40.4"; 20.04 is EOL for standard support)
- **PostgreSQL 17** — one cluster; control-plane database (tenant registry with status +
  plan tier, master catalogs, admin users) + a dedicated database per tenant. Billing is out
  of scope for v1 but attaches here later, keyed by tenant id (§9.10)
- **MaluDB** — activity memory, fed by the log ingestion job
- **Apache + PHP + HTMX + Bootstrap 5.3** — server-rendered per the plugin's design system and
  PHP patterns; login/session per php-session-auth
- **Chart.js** — the single charting library for all graphs (§7); served locally, initialized
  via the HTMX swap lifecycle
- **Python + FastMCP** — the two client-facing read MCP servers (reverse-proxied by Apache)
  plus the localhost-only actions MCP server
- **Claude Agent SDK (Python)** — the unified assistant behind the AMA page *and* the command
  bar; Apache reverse-proxies `/assistant/stream` for SSE

## 9. Open questions (next planning pass)

1. **Food database seeding** — curated seed vs. USDA FoodData Central import. Decides food
   search quality on day one.
2. **Barcode / photo food scanning** — MyFitnessPal's signature conveniences; both need
   camera + lookup services. Proposed: out of v1, revisit as an upgrade trigger.
3. **Recipes & saved meals** — MFP-style recipe builder (ingredients → computed nutrition)
   and one-tap repeat meals. Proposed: saved meals v1, recipe builder v1.1.
4. **Supersets / drop sets / rest timer** — Hevy-style logging niceties in the guided workout.
   Rest timer is cheap; advanced set types complicate the template model. Proposed: timer v1,
   advanced set types later.
5. ~~Client self-service goals~~ — **resolved**: clients can set their own nutrition goals
   (by screen or by voice); coaches see changes via goal history and the activity log.
   A per-tenant "coach-approval required" switch can come later if a coaching business asks.
6. **Catalog sync mechanics** — how control-plane catalog updates propagate to tenant
   databases (job cadence, conflict rules with tenant customizations).
7. **Units** — metric, imperial, or per-client preference?
8. **DEXA entry method** — manual entry of scan numbers in v1; importing a provider's PDF
   report is a later convenience.
9. **Diet program templates** — reusable meal-plan programs (the diet twin of training
   programs). Deferred by decision: fitness programs first; v1 diet prescription is the
   nutrition-goals system. Revisit once training programs are proven.
10. ~~Tenant billing~~ — **out of scope for this version** (decision 2026-08-05), but the
    database is shaped so it bolts on later without migration pain:
    - Billing is strictly a **control-plane concern**, keyed by tenant id. Adding it later
      means new control-plane tables (subscription, plan, invoice) referencing the tenant
      registry — **no tenant database ever changes**, because no billing data ever lives in
      one.
    - The tenant registry carries a **status** (trial / active / suspended) and a **plan
      tier** field from day one — operationally useful now (provisioning, admin dashboard,
      access gating) and exactly the seam a payment provider hooks into later.
    - Client-facing code never checks billing directly; it checks tenant status — so wiring
      Stripe in later changes what *sets* the status, not what *reads* it.

## 9. Research notes (what we borrowed)

- **MyFitnessPal** — food diary by meal over a huge preloaded database; custom foods; custom
  macro goals in grams; per-day-of-week goals; weekly view of averages vs. goal; recipes and
  saved meals; barcode/meal scan (deferred here).
- **Hevy / Strong** — routine builder; "start routine" flow that guides logging set-by-set with
  previous performance shown; custom exercises; rest timers, RPE; progress per exercise *and
  per muscle group*; PRs.
- **Fitbod** — every exercise tagged with primary/secondary muscle targets; per-muscle-group
  scoring (recovery/volume percentages, heat map). The direct precedent for Zobifit's
  admin-managed muscle taxonomy + weighted exercise scoring.
