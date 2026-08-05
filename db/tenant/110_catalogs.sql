-- Tenant database: catalog copies + tenant/client custom entries.
-- provenance: 'catalog' rows are synced copies of master rows (master_id set, read-only
-- in the app); 'tenant' rows were added by a coach for the whole tenant; 'client' rows
-- were added by one client (owner_user_id set). Muscle groups and equipment are
-- admin-only: catalog provenance only, per the plan.

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE TABLE muscle_groups (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    master_id  bigint NOT NULL UNIQUE,                -- master_muscle_groups.id
    name       text NOT NULL,
    sort_order int  NOT NULL DEFAULT 0,
    is_active  boolean NOT NULL DEFAULT true,
    synced_at  timestamptz NOT NULL DEFAULT now(),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER muscle_groups_touch BEFORE UPDATE ON muscle_groups
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE equipment (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    master_id  bigint NOT NULL UNIQUE,                -- master_equipment.id
    name       text NOT NULL,
    is_active  boolean NOT NULL DEFAULT true,
    synced_at  timestamptz NOT NULL DEFAULT now(),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER equipment_touch BEFORE UPDATE ON equipment
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

ALTER TABLE client_equipment
    ADD CONSTRAINT client_equipment_equipment_fk
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE;

CREATE TABLE exercises (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    master_id     bigint UNIQUE,                      -- set only for provenance='catalog'
    provenance    text NOT NULL CHECK (provenance IN ('catalog', 'tenant', 'client')),
    owner_user_id bigint REFERENCES users(id),        -- set only for provenance='client'
    name          text NOT NULL,
    equipment_id  bigint REFERENCES equipment(id),    -- NULL = no equipment (bodyweight)
    instructions  text,
    is_active     boolean NOT NULL DEFAULT true,
    synced_at     timestamptz,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    CHECK (provenance <> 'catalog' OR master_id IS NOT NULL),
    CHECK (provenance = 'client'  OR owner_user_id IS NULL)
);
CREATE TRIGGER exercises_touch BEFORE UPDATE ON exercises
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX exercises_name_trgm ON exercises USING gin (name gin_trgm_ops);

-- Muscle weightings: whoever adds an exercise sets these (admin via sync, coach or
-- client via the custom-exercise form). Scoring and substitution compute over them.
CREATE TABLE exercise_muscles (
    exercise_id     bigint NOT NULL REFERENCES exercises(id) ON DELETE CASCADE,
    muscle_group_id bigint NOT NULL REFERENCES muscle_groups(id),
    weight          smallint NOT NULL CHECK (weight BETWEEN 1 AND 100),
    PRIMARY KEY (exercise_id, muscle_group_id)
);

CREATE TABLE foods (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    master_id     bigint UNIQUE,
    provenance    text NOT NULL CHECK (provenance IN ('catalog', 'tenant', 'client')),
    owner_user_id bigint REFERENCES users(id),
    name          text NOT NULL,
    brand         text,
    serving_qty   numeric(8,2) NOT NULL CHECK (serving_qty > 0),
    serving_unit  text NOT NULL,
    calories_kcal numeric(7,1) NOT NULL CHECK (calories_kcal >= 0),
    protein_g     numeric(6,1) NOT NULL DEFAULT 0 CHECK (protein_g >= 0),
    carbs_g       numeric(6,1) NOT NULL DEFAULT 0 CHECK (carbs_g >= 0),
    fat_g         numeric(6,1) NOT NULL DEFAULT 0 CHECK (fat_g >= 0),
    is_active     boolean NOT NULL DEFAULT true,
    synced_at     timestamptz,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    CHECK (provenance <> 'catalog' OR master_id IS NOT NULL),
    CHECK (provenance = 'client'  OR owner_user_id IS NULL)
);
CREATE TRIGGER foods_touch BEFORE UPDATE ON foods
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX foods_name_trgm ON foods USING gin (name gin_trgm_ops);

CREATE TABLE measurement_types (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    master_id     bigint UNIQUE,
    provenance    text NOT NULL CHECK (provenance IN ('catalog', 'tenant', 'client')),
    owner_user_id bigint REFERENCES users(id),
    name          text NOT NULL,
    unit          text NOT NULL,
    category      text NOT NULL CHECK (category IN ('body', 'dexa')),
    sort_order    int  NOT NULL DEFAULT 0,
    is_active     boolean NOT NULL DEFAULT true,
    synced_at     timestamptz,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    CHECK (provenance <> 'catalog' OR master_id IS NOT NULL),
    CHECK (provenance = 'client'  OR owner_user_id IS NULL)
);
CREATE TRIGGER measurement_types_touch BEFORE UPDATE ON measurement_types
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
