-- Tenant database: diet — food diary, nutrition goals, saved meals.

-- The atomic diet fact: client ate `servings` of a food, on a date, under a meal.
CREATE TABLE food_log_entries (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_user_id bigint NOT NULL REFERENCES users(id),
    food_id        bigint NOT NULL REFERENCES foods(id),
    eaten_on       date NOT NULL,
    meal           text NOT NULL CHECK (meal IN ('breakfast', 'lunch', 'dinner', 'snack')),
    servings       numeric(6,2) NOT NULL CHECK (servings > 0 AND servings <= 100),
    logged_at      timestamptz NOT NULL DEFAULT now(),
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER food_log_entries_touch BEFORE UPDATE ON food_log_entries
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX food_log_entries_client_day_idx ON food_log_entries (client_user_id, eaten_on);
-- recent/frequent quick-add is a query over this index:
CREATE INDEX food_log_entries_client_food_idx ON food_log_entries (client_user_id, food_id, eaten_on);

-- Nutrition goals: superseded, never overwritten (PLAN.md §3). The current goal for a
-- given (client, period, day_of_week) is the row with the greatest effective_from <= today,
-- created_at as tiebreak. period='daily' + day_of_week NULL = default for all days;
-- day_of_week 0(Sun)–6(Sat) overrides the default for that weekday (the MFP Premium
-- pattern). period='weekly' rows hold weekly totals (day_of_week must be NULL).
CREATE TABLE nutrition_goals (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_user_id  bigint NOT NULL REFERENCES users(id),
    period          text NOT NULL CHECK (period IN ('daily', 'weekly')),
    day_of_week     smallint CHECK (day_of_week BETWEEN 0 AND 6),
    calories_kcal   numeric(7,1) CHECK (calories_kcal > 0),
    protein_g       numeric(6,1) CHECK (protein_g >= 0),
    carbs_g         numeric(6,1) CHECK (carbs_g >= 0),
    fat_g           numeric(6,1) CHECK (fat_g >= 0),
    effective_from  date NOT NULL,
    set_by_user_id  bigint NOT NULL REFERENCES users(id),   -- coach or the client themselves
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CHECK (period = 'daily' OR day_of_week IS NULL),
    CHECK (calories_kcal IS NOT NULL OR protein_g IS NOT NULL
           OR carbs_g IS NOT NULL OR fat_g IS NOT NULL)
);
CREATE TRIGGER nutrition_goals_touch BEFORE UPDATE ON nutrition_goals
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX nutrition_goals_current_idx
    ON nutrition_goals (client_user_id, period, day_of_week, effective_from DESC);

-- Saved meals: one-tap repeat logging (PLAN.md §9.3 — saved meals in v1, recipe builder
-- with computed nutrition deferred).
CREATE TABLE saved_meals (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    owner_user_id bigint NOT NULL REFERENCES users(id),
    name          text NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER saved_meals_touch BEFORE UPDATE ON saved_meals
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE saved_meal_items (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    saved_meal_id bigint NOT NULL REFERENCES saved_meals(id) ON DELETE CASCADE,
    food_id       bigint NOT NULL REFERENCES foods(id),
    servings      numeric(6,2) NOT NULL CHECK (servings > 0),
    created_at    timestamptz NOT NULL DEFAULT now()
);
