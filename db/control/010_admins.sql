-- zobifit_control: platform administrators.
-- Auth structures per the php-session-auth skill: nullable password_hash (Google-only
-- accounts), auth_identities keyed on the provider 'sub', TOTP columns + recovery codes.

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TABLE admins (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email              text NOT NULL UNIQUE,          -- stored normalized: lower(trim())
    password_hash      text,                          -- NULL = Google-only account
    display_name       text NOT NULL,
    status             text NOT NULL DEFAULT 'active'
                       CHECK (status IN ('active', 'suspended')),
    totp_secret        text,                          -- encrypted at rest (libsodium, key in env)
    totp_enabled_at    timestamptz,                   -- NULL = 2FA off
    totp_last_timestep bigint,                        -- replay guard
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER admins_touch BEFORE UPDATE ON admins
    FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE admin_auth_identities (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    admin_id          bigint NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
    provider          text NOT NULL,                  -- 'google'
    provider_user_id  text NOT NULL,                  -- Google's stable 'sub' claim
    email_at_provider text NOT NULL,
    created_at        timestamptz NOT NULL DEFAULT now(),
    UNIQUE (provider, provider_user_id)
);

CREATE TABLE admin_totp_recovery_codes (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    admin_id   bigint NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
    code_hash  text NOT NULL,                         -- password_hash() of the code
    used_at    timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE admin_login_attempts (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email        text NOT NULL,                       -- as submitted (normalized)
    ip           inet NOT NULL,
    success      boolean NOT NULL,
    attempted_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX admin_login_attempts_email_idx ON admin_login_attempts (email, attempted_at);
CREATE INDEX admin_login_attempts_ip_idx    ON admin_login_attempts (ip, attempted_at);

CREATE TABLE admin_password_resets (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    admin_id   bigint NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
    token_hash text NOT NULL,
    expires_at timestamptz NOT NULL,
    used_at    timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);
