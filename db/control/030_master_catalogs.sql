-- zobifit_control: master catalogs, admin-managed, synced into every tenant database.
-- Sync contract (db/README.md): tenant copies match on master id; is_active=false
-- propagates instead of deletion; updated_at drives incremental sync.

CREATE EXTENSION IF NOT EXISTS pg_trgm;   -- food/exercise name search, here and in tenants

CREATE TABLE master_muscle_groups (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name       text NOT NULL UNIQUE,                  -- chest, lats, quads, hamstrings, ...
    sort_order int  NOT NULL DEFAULT 0,
    is_active  boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER master_muscle_groups_touch BEFORE UPDATE ON master_muscle_groups
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE master_equipment (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name       text NOT NULL UNIQUE,                  -- barbell, dumbbell, cable machine, ...
    is_active  boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER master_equipment_touch BEFORE UPDATE ON master_equipment
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE master_exercises (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name         text NOT NULL UNIQUE,
    equipment_id bigint REFERENCES master_equipment(id),   -- NULL = no equipment (bodyweight)
    instructions text,
    is_active    boolean NOT NULL DEFAULT true,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER master_exercises_touch BEFORE UPDATE ON master_exercises
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX master_exercises_name_trgm ON master_exercises USING gin (name gin_trgm_ops);

-- The muscle weighting system (Fitbod model): 0–100 per muscle group per exercise.
-- Scoring and substitution similarity are computed over these vectors.
CREATE TABLE master_exercise_muscles (
    exercise_id     bigint NOT NULL REFERENCES master_exercises(id) ON DELETE CASCADE,
    muscle_group_id bigint NOT NULL REFERENCES master_muscle_groups(id),
    weight          smallint NOT NULL CHECK (weight BETWEEN 1 AND 100),
    PRIMARY KEY (exercise_id, muscle_group_id)
);

CREATE TABLE master_foods (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name        text NOT NULL,
    brand       text,
    serving_qty  numeric(8,2) NOT NULL CHECK (serving_qty > 0),
    serving_unit text NOT NULL,                       -- g, ml, piece, cup, ...
    calories_kcal numeric(7,1) NOT NULL CHECK (calories_kcal >= 0),
    protein_g   numeric(6,1) NOT NULL DEFAULT 0 CHECK (protein_g >= 0),
    carbs_g     numeric(6,1) NOT NULL DEFAULT 0 CHECK (carbs_g >= 0),
    fat_g       numeric(6,1) NOT NULL DEFAULT 0 CHECK (fat_g >= 0),
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (name, brand)
);
CREATE TRIGGER master_foods_touch BEFORE UPDATE ON master_foods
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX master_foods_name_trgm ON master_foods USING gin (name gin_trgm_ops);

CREATE TABLE master_measurement_types (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name       text NOT NULL UNIQUE,                  -- weight, waist, body fat %, lean mass, ...
    unit       text NOT NULL,                         -- kg, cm, %, g/cm² ...
    category   text NOT NULL CHECK (category IN ('body', 'dexa')),
    sort_order int  NOT NULL DEFAULT 0,
    is_active  boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER master_measurement_types_touch BEFORE UPDATE ON master_measurement_types
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
