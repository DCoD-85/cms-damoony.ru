-- Выбранные пользователем алиасы установки (JSON/comma); NULL = все алиасы сценария
ALTER TABLE installations ADD COLUMN IF NOT EXISTS aliases TEXT DEFAULT NULL;
