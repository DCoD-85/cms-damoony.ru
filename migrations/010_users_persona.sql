-- Связь локальной учётной записи с persona (SSO)
ALTER TABLE users ADD COLUMN IF NOT EXISTS persona_id BIGINT UNSIGNED DEFAULT NULL;
CREATE INDEX IF NOT EXISTS idx_users_persona ON users (persona_id);
