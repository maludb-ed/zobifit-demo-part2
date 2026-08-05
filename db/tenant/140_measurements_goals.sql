-- Tenant database: body measurements (tape/scale + DEXA) and goals.

-- One value per type per date per source. A DEXA visit is a batch of rows sharing
-- measured_on + source='dexa'. Trend graphs are queries over this table.
CREATE TABLE measurement_entries (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_user_id      bigint NOT NULL REFERENCES users(id),
    measurement_type_id bigint NOT NULL REFERENCES measurement_types(id),
    value               numeric(8,2) NOT NULL,
    measured_on         date NOT NULL,
    source              text NOT NULL DEFAULT 'manual' CHECK (source IN ('manual', 'dexa')),
    note                text,
    created_by          bigint NOT NULL REFERENCES users(id),   -- client, or coach on their behalf
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now(),
    UNIQUE (client_user_id, measurement_type_id, measured_on, source)
);
CREATE TRIGGER measurement_entries_touch BEFORE UPDATE ON measurement_entries
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX measurement_entries_trend_idx
    ON measurement_entries (client_user_id, measurement_type_id, measured_on);

-- Goals (PLAN.md §3): target any measurement type, a strength target, a consistency
-- target, or nutrition adherence. Progress is always computed from logged facts.
-- baseline_value is captured at creation so progress % has a denominator.
CREATE TABLE goals (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_user_id      bigint NOT NULL REFERENCES users(id),
    goal_type           text NOT NULL
                        CHECK (goal_type IN ('measurement', 'strength', 'consistency', 'nutrition')),
    measurement_type_id bigint REFERENCES measurement_types(id),  -- measurement goals
    exercise_id         bigint REFERENCES exercises(id),          -- strength goals
    target_value        numeric(8,2),   -- measurement value / strength weight_kg / weekly %-adherence
    target_reps         smallint CHECK (target_reps BETWEEN 1 AND 100),   -- strength: at N reps
    target_per_week     smallint CHECK (target_per_week BETWEEN 1 AND 14), -- consistency: workouts/week
    baseline_value      numeric(8,2),
    target_date         date,
    status              text NOT NULL DEFAULT 'open'
                        CHECK (status IN ('open', 'achieved', 'abandoned')),
    set_by_user_id      bigint NOT NULL REFERENCES users(id),
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now(),
    CHECK (goal_type <> 'measurement'  OR (measurement_type_id IS NOT NULL AND target_value IS NOT NULL)),
    CHECK (goal_type <> 'strength'     OR (exercise_id IS NOT NULL AND target_value IS NOT NULL)),
    CHECK (goal_type <> 'consistency'  OR target_per_week IS NOT NULL)
);
CREATE TRIGGER goals_touch BEFORE UPDATE ON goals
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX goals_client_idx ON goals (client_user_id, status);
