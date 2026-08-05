# Zobifit MCP tool surface (Phase 1)

Per the mcp-servers skill: two client-facing **read-only** servers per tenant, Python +
FastMCP, streamable HTTP, systemd + Apache reverse proxy at
`https://{slug}.zobifit.com/mcp/records` and `/mcp/activity`, per-client bearer tokens
(`mcp_access_tokens`, hashed, revocable). A third localhost-only actions server is specified
in the action manifest, not here. The Phase 0 question list is the contract; this document
is complete only if every §2 question maps to a tool.

DB roles: `zobifit_mcp_records_ro` and `zobifit_mcp_activity_ro` — SELECT-only,
`default_transaction_read_only`, 5 s statement timeout (db/control/001_roles.sql).
Every tool: `readOnlyHint: true`, pydantic input models with `extra="forbid"`, limit/offset
pagination, actionable error messages. Every tool call logs to `activity_log`.

Authorization inside a tenant: a token maps to the tenant (database-level isolation — SaaS
Plus+). Client-scoped tools take `client` as a name or id, resolved server-side; when the
caller's token is client-issued, tools are locked to that client's id.

## Record server — `zobifit_records_mcp` (PostgreSQL)

### People & overview

| Tool | Params | Answers |
|---|---|---|
| `list_clients` | status?, coach?, limit/offset | who's on the roster, per coach |
| `get_client_overview` | client | one client's full picture: profile, current program + week/day, current goals with progress, latest measurements, 7-day adherence |
| `list_clients_missing_logs` | days, kind = food\|workout\|either | "who hasn't logged in N days?" |

### Diet

| Tool | Params | Answers |
|---|---|---|
| `get_nutrition_log` | client, date_from, date_to | "what did I eat yesterday / last Tuesday?" — entries by day and meal with totals |
| `get_nutrition_adherence` | client, period = daily\|weekly, date? | consumed vs. goal vs. **remaining** ("how much protein left today?", "this week's average vs. weekly goal") |
| `get_nutrition_goals` | client, include_history? | current daily (incl. per-weekday) + weekly goals; history = "what were they before?" |

### Training

| Tool | Params | Answers |
|---|---|---|
| `list_active_programs` | client | ALL active assignments (concurrent programs are allowed): program, focus, structure, start date, current week/day |
| `get_program_schedule` | client, program?, date? | week × day grid **with substitutions applied**; date → today's workout(s) across every active program; `program` (name/focus → resolved) disambiguates when several are active |
| `list_workout_templates` | muscle_emphasis?, search? | "what full-body templates are there?" (emphasis computed from member exercises' weightings) |
| `get_workout_history` | client, exercise?, muscle_group?, limit | "when did I last do bench press / train legs?" — sessions filtered by exercise or by muscle-group involvement |
| `get_previous_workout` | client, template? | last completed same-type session, all sets, prescribed-vs-actual — powers "what did I do last leg day?" and copy-as-base |
| `get_exercise_progression` | client, exercise, weeks | best set per session over time — "is my squat going up?" |
| `get_volume_trend` | client, template? \| muscle_group?, weeks | session volume over time — "is progressive overload working?" |
| `get_personal_records` | client, exercise? | "my max bench ever", PRs in a period |
| `get_muscle_group_scores` | client, date_from, date_to | weekly volume per muscle group from sets × weightings — "what's neglected?" |
| `suggest_exercise_substitutes` | exercise, client?, limit | same-muscle alternatives ranked by weighting similarity, filtered by the client's equipment |

### Measurements & goals

| Tool | Params | Answers |
|---|---|---|
| `get_measurement_history` | client, type?, source?, date_from?, date_to? | trends ("weight over time"); source=dexa groups by scan date → "last DEXA vs. the one before" |
| `list_goals` | client?, status? | goals **with computed progress** — "am I on track?" / coach: "which clients are off track?" |
| `get_goal_progress` | goal_id | one goal in detail: baseline → current → target, trajectory vs. target_date |

### Calculators (deterministic math in code — the model never does arithmetic)

| Tool | Params | Answers |
|---|---|---|
| `calculate_bmi` | client | BMI from latest weight + profile height |
| `calculate_bmr_tdee` | client | BMR (Mifflin-St Jeor; auto-upgrades to Katch-McArdle when a body-fat % entry exists) and TDEE = BMR × activity level |
| `calculate_calorie_target` | client, rate (e.g. "-0.45 kg/week") | TDEE minus/plus the deficit/surplus for the rate — "max calories to lose 1 lb a week" |

### Coaching operations

| Tool | Params | Answers |
|---|---|---|
| `list_programs_ending` | within_weeks | "who needs new programming?" |

### Long tail

| Tool | Params | Answers |
|---|---|---|
| `records_search` | sql (single SELECT) | everything else. Validated in code (SELECT-only, no multiple statements), read-only role, 5 s timeout, 200-row cap. Description embeds a schema summary. |

## Activity server — `zobifit_activity_mcp` (MaluDB)

| Tool | Params | Answers |
|---|---|---|
| `get_actor_timeline` | user, date_from?, date_to?, actions? | "when did Maria stop logging regularly?" — an actor's trail |
| `get_record_history` | entity, entity_id | before/after trail of one record — "when did I last adjust Jake's program, and what did I change?" / "when did my coach update my plan?" |
| `who_touched` | entity, entity_id?, action?, since? | "did anyone view the program I published yesterday?" |
| `activity_search` | query | guarded long-tail search over the activity stream (validated, capped) |

## Question → tool traceability (the §2 contract)

| PLAN.md §2 question | Tool |
|---|---|
| Clients not logging in N days | `list_clients_missing_logs` |
| Maria hitting calorie/protein goals (day + week) | `get_nutrition_adherence` |
| Jake: actual vs. prescribed last session | `get_previous_workout` |
| Same muscles as barbell rows, dumbbells only | `suggest_exercise_substitutes` |
| Jake's squat progress over 8 weeks | `get_exercise_progression` |
| Jake's volume trending up? | `get_volume_trend` |
| Jake's muscle groups this week / neglected | `get_muscle_group_scores` |
| Programs ending within 2 weeks | `list_programs_ending` |
| Maria's current + prior macro goals | `get_nutrition_goals` |
| Clients on/off track for goals | `list_goals` |
| Clients improved body comp since last DEXA | `records_search` (comparative long tail; promote to a named tool if it recurs) |
| When did I adjust Jake's program + what changed (A) | `get_record_history` |
| When did Maria stop logging (A) | `get_actor_timeline` |
| Anyone view the published program (A) | `who_touched` |
| My workout today / program week+day | `get_program_schedule` (returns one entry per active program) |
| No cable machine — what instead | `suggest_exercise_substitutes` |
| This exercise last time | `get_workout_history` |
| Previous workout of this type | `get_previous_workout` |
| More volume than last time / trend | `get_volume_trend` |
| Calories/protein left today | `get_nutrition_adherence` |
| Week average vs. weekly goal | `get_nutrition_adherence` |
| BMI / BMR / calories burned per day | `calculate_bmi`, `calculate_bmr_tdee` |
| Max calories to lose 1 lb/week | `calculate_calorie_target` |
| What did I eat yesterday / last Tuesday | `get_nutrition_log` |
| Best squat set / PRs this month | `get_personal_records` |
| Last time I trained legs | `get_workout_history` (muscle_group) |
| Full-body templates available | `list_workout_templates` |
| Last time I did bench press | `get_workout_history` (exercise) |
| Max bench ever | `get_personal_records` |
| Muscle groups hit this week / balance | `get_muscle_group_scores` |
| Weight / waist / body-fat trend | `get_measurement_history` |
| Last DEXA vs. previous | `get_measurement_history` (source=dexa) |
| On track for each goal | `list_goals`, `get_goal_progress` |
| When did coach last update my plan (A) | `get_record_history` |

**Admin questions** (tenant counts, catalog completeness, tenant usage) are served by admin
screens over `zobifit_control` + per-tenant connections — the admin is not a tenant-MCP
consumer in v1. Revisit if a control-plane MCP earns its keep.
