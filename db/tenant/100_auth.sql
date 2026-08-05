-- Tenant database: users and authentication.
-- One database per tenant (SaaS Plus+); roles here are owner/coach/client. Platform
-- admins live in zobifit_control, never here.
-- Auth structures per the php-session-auth skill: nullable password_hash, auth_identities
-- on the Google 'sub', TOTP columns + recovery codes. Invite-only in v1: users are
-- created by invitation, never self-registered.

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TABLE users (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email              text NOT NULL UNIQUE,          -- stored normalized: lower(trim())
    password_hash      text,                          -- NULL = Google-only account
    display_name       text NOT NULL,
    role               text NOT NULL CHECK (role IN ('owner', 'coach', 'client')),
    status             text NOT NULL DEFAULT 'invited'
                       CHECK (status IN ('invited', 'active', 'paused', 'archived')),
    assigned_coach_id  bigint REFERENCES users(id),   -- clients only: their coach
    totp_secret        text,                          -- encrypted at rest (libsodium, key in env)
    totp_enabled_at    timestamptz,                   -- NULL = 2FA off
    totp_last_timestep bigint,                        -- replay guard
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),
    CHECK (role = 'client' OR assigned_coach_id IS NULL)
);
CREATE TRIGGER users_touch BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
CREATE INDEX users_coach_idx ON users (assigned_coach_id) WHERE role = 'client';

CREATE TABLE auth_identities (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id           bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider          text NOT NULL,                  -- 'google'
    provider_user_id  text NOT NULL,                  -- Google's stable 'sub' claim
    email_at_provider text NOT NULL,
    created_at        timestamptz NOT NULL DEFAULT now(),
    UNIQUE (provider, provider_user_id)
);

CREATE TABLE totp_recovery_codes (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id    bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash  text NOT NULL,                         -- password_hash() of the code
    used_at    timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE login_attempts (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email        text NOT NULL,
    ip           inet NOT NULL,
    success      boolean NOT NULL,
    attempted_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX login_attempts_email_idx ON login_attempts (email, attempted_at);
CREATE INDEX login_attempts_ip_idx    ON login_attempts (ip, attempted_at);

CREATE TABLE password_resets (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id    bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash text NOT NULL,
    expires_at timestamptz NOT NULL,
    used_at    timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE invitations (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email       text NOT NULL,                        -- normalized
    role        text NOT NULL CHECK (role IN ('coach', 'client')),
    invited_by  bigint NOT NULL REFERENCES users(id),
    token_hash  text NOT NULL,
    expires_at  timestamptz NOT NULL,
    accepted_at timestamptz,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX invitations_email_idx ON invitations (email);

-- Client profile: the facts the calculators need (PLAN.md §3). Current weight and
-- body-fat % come from measurement_entries, never from here.
CREATE TABLE client_profiles (
    user_id          bigint PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    birthdate        date,
    sex              text NOT NULL DEFAULT 'unspecified'
                     CHECK (sex IN ('male', 'female', 'unspecified')),  -- BMR formula input
    height_cm        numeric(5,1) CHECK (height_cm BETWEEN 50 AND 280),
    activity_level   text CHECK (activity_level IN
                     ('sedentary', 'light', 'moderate', 'very', 'extra')),  -- TDEE multiplier
    units_preference text NOT NULL DEFAULT 'imperial'
                     CHECK (units_preference IN ('metric', 'imperial')),
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER client_profiles_touch BEFORE UPDATE ON client_profiles
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- Which equipment this client can actually use — drives exercise substitution.
CREATE TABLE client_equipment (
    user_id      bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    equipment_id bigint NOT NULL,                     -- FK added in 110_catalogs.sql
    created_at   timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, equipment_id)
);
