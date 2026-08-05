-- Tenant database: training — templates, programs, assignments, substitutions,
-- sessions, logged sets.

CREATE TABLE workout_templates (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name        text NOT NULL,
    description text,
    created_by  bigint NOT NULL REFERENCES users(id),
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER workout_templates_touch BEFORE UPDATE ON workout_templates
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- Prescription per exercise within a template. Load may be absolute (kg), %-of-max, or
-- RPE-based; any combination of the three may be present (coach's choice).
CREATE TABLE template_exercises (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    template_id     bigint NOT NULL REFERENCES workout_templates(id) ON DELETE CASCADE,
    position        int NOT NULL,                     -- order within the workout
    exercise_id     bigint NOT NULL REFERENCES exercises(id),
    target_sets     smallint NOT NULL CHECK (target_sets BETWEEN 1 AND 20),
    target_reps_low smallint NOT NULL CHECK (target_reps_low BETWEEN 1 AND 200),
    target_reps_high smallint CHECK (target_reps_high >= target_reps_low),  -- NULL = fixed reps
    target_load_kg  numeric(6,2) CHECK (target_load_kg >= 0),
    target_load_pct smallint CHECK (target_load_pct BETWEEN 1 AND 150),     -- % of 1RM
    target_rpe      numeric(3,1) CHECK (target_rpe BETWEEN 1 AND 10),
    rest_seconds    smallint CHECK (rest_seconds BETWEEN 0 AND 900),
    note            text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (template_id, position)
);
CREATE TRIGGER template_exercises_touch BEFORE UPDATE ON template_exercises
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- The coach's reusable product: duration × frequency + the week×day schedule grid.
-- focus labels the program's purpose (muscle building, endurance, mobility, ...) so
-- concurrent programs are distinguishable in the UI and by voice ("start today's
-- endurance workout"). Free text — coaches name their own methodologies.
CREATE TABLE programs (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name           text NOT NULL,
    focus          text,
    description    text,
    weeks          smallint NOT NULL CHECK (weeks BETWEEN 1 AND 104),
    days_per_week  smallint NOT NULL CHECK (days_per_week BETWEEN 1 AND 7),
    repeats_weekly boolean NOT NULL DEFAULT true,     -- true: one weekly pattern for all weeks
    created_by     bigint NOT NULL REFERENCES users(id),
    is_active      boolean NOT NULL DEFAULT true,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER programs_touch BEFORE UPDATE ON programs
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- Schedule grid. week_number NULL = the repeating weekly pattern (repeats_weekly=true);
-- specific week_number rows express week-by-week variation and override the pattern for
-- that week (deloads, progression blocks).
CREATE TABLE program_slots (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    program_id          bigint NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
    week_number         smallint CHECK (week_number >= 1),
    day_number          smallint NOT NULL CHECK (day_number BETWEEN 1 AND 7),
    workout_template_id bigint NOT NULL REFERENCES workout_templates(id),
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now(),
    UNIQUE NULLS NOT DISTINCT (program_id, week_number, day_number)
);
CREATE TRIGGER program_slots_touch BEFORE UPDATE ON program_slots
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- History kept: a client's past programs stay answerable ("what was he running last
-- spring?"). A client may run SEVERAL programs concurrently (e.g. muscle building +
-- endurance); each assignment ends independently (completed by schedule or explicitly).
CREATE TABLE program_assignments (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_user_id bigint NOT NULL REFERENCES users(id),
    program_id     bigint NOT NULL REFERENCES programs(id),
    start_date     date NOT NULL,
    status         text NOT NULL DEFAULT 'active'
                   CHECK (status IN ('active', 'completed', 'abandoned')),
    assigned_by    bigint NOT NULL REFERENCES users(id),
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER program_assignments_touch BEFORE UPDATE ON program_assignments
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX program_assignments_client_idx ON program_assignments (client_user_id, status);
-- concurrent programs are allowed; only a duplicate active assignment of the SAME
-- program is prevented:
CREATE UNIQUE INDEX program_assignments_active_program
    ON program_assignments (client_user_id, program_id) WHERE status = 'active';

-- Per-client exercise swap within an assignment (PLAN.md §3): the master program is never
-- modified; the swap applies to every future session this assignment generates.
CREATE TABLE assignment_substitutions (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    assignment_id    bigint NOT NULL REFERENCES program_assignments(id) ON DELETE CASCADE,
    from_exercise_id bigint NOT NULL REFERENCES exercises(id),
    to_exercise_id   bigint NOT NULL REFERENCES exercises(id),
    reason           text,                            -- e.g. 'no cable machine'
    created_by       bigint NOT NULL REFERENCES users(id),
    created_at       timestamptz NOT NULL DEFAULT now(),
    UNIQUE (assignment_id, from_exercise_id),
    CHECK (from_exercise_id <> to_exercise_id)
);

-- A performed (or in-progress) workout. The source template types the session:
-- "previous workout of the same type" = latest completed session with the same
-- workout_template_id. seeded_from_session_id records the copy-previous flow.
CREATE TABLE workout_sessions (
    id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_user_id         bigint NOT NULL REFERENCES users(id),
    session_date           date NOT NULL,
    started_at             timestamptz NOT NULL DEFAULT now(),
    completed_at           timestamptz,
    workout_template_id    bigint REFERENCES workout_templates(id),   -- NULL = ad-hoc
    program_assignment_id  bigint REFERENCES program_assignments(id),
    program_week           smallint,
    program_day            smallint,
    seeded_from_session_id bigint REFERENCES workout_sessions(id),
    status                 text NOT NULL DEFAULT 'in_progress'
                           CHECK (status IN ('in_progress', 'completed', 'abandoned')),
    notes                  text,
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER workout_sessions_touch BEFORE UPDATE ON workout_sessions
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX workout_sessions_client_date_idx ON workout_sessions (client_user_id, session_date);
CREATE INDEX workout_sessions_same_type_idx
    ON workout_sessions (client_user_id, workout_template_id, session_date DESC)
    WHERE status = 'completed';

-- The atomic training fact. Progress, PRs, muscle scores, and session volume are all
-- queries over these (volume = reps × weight_kg, weighted per muscle group via
-- exercise_muscles).
CREATE TABLE logged_sets (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    session_id  bigint NOT NULL REFERENCES workout_sessions(id) ON DELETE CASCADE,
    exercise_id bigint NOT NULL REFERENCES exercises(id),
    set_number  smallint NOT NULL CHECK (set_number BETWEEN 1 AND 50),
    reps        smallint NOT NULL CHECK (reps BETWEEN 1 AND 500),
    weight_kg   numeric(6,2) CHECK (weight_kg BETWEEN 0 AND 1000),   -- NULL = bodyweight
    rpe         numeric(3,1) CHECK (rpe BETWEEN 1 AND 10),
    logged_at   timestamptz NOT NULL DEFAULT now(),
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (session_id, exercise_id, set_number)
);
CREATE TRIGGER logged_sets_touch BEFORE UPDATE ON logged_sets
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX logged_sets_exercise_idx ON logged_sets (exercise_id, logged_at);
