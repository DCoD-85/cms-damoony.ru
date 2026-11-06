# CLI-команды

Точка входа: `php bin/cli.php [command] [--key=value]`. Возвращает `Result` (код 0 при успехе, 1 при ошибке).

**Принцип CLI-First:** CLI — единственный источник истины для файловых операций. Админка и AI-агенты (`bin/aicli.php`) делегируют работу ему.

⚠️ **Правила безопасности:**
- **Path Traversal Guard:** все команды с `--domain`/`--name` проверяют на `..` и regex. `--domain=a..ru` отклоняется.
- **Карантин (6 уровней):** все destructive-команды (`*:remove`, `trash:clear`) перемещают файлы в папку `*_trash_[timestamp]/`, а не удаляют сразу. Требуют флаг `--force`.
- **realDomain:** если `domains_real["alias.ru"] = "real.ru"`, пути в `app/` строятся по `real.ru`.

---

## 10. AIUI Сессии (Session-based workflow)

Команды для изолированной работы AIUI (`www/admin/ai_edit.php`). Все пишут в `app/.sess_{tab}.{domain}/` и `www/media/.sess_{tab}.{domain}/`, не трогая production.

- `session:start --domain=[d] --tab=[hex] [--admin-sess=login]` — копирует `app/{domain}/` и `www/media/{domain}/` в `.sess_{tab}`. Пишет `.session.json` с permissions.
- `session:apply --domain=[d] --tab=[hex] [--admin-sess=login]` — атомарно переносит `.sess_{tab}` в production (с optimistic lock по `production_hash`). Старая папка → `_trash_sess_*`.
- `session:discard --domain=[d] --tab=[hex]` — переносит `.sess_{tab}` в карантин.
- `session:list [--domain=d]` — сканирует `app/.sess_*`, читает meta.

**Флаг `--session=tab`** — обязательный для `page:new`, `page:write`, `block:write`, `filler:write`, `skin:edit`, `scheme:save` при работе из AIUI. Без флага команда пишет в production.

---

## 11. DB & DataProvider (read-only + safe write)

Команды для introspection схемы БД и безопасного вызова методов провайдеров. Используются AI-агентами (aicli) и админкой. Никакого сырого SQL от AI — только именованные методы (см. `v3.1-design.md` §3, §6).

### 11.1. Introspection (read-only)

- `db:schema --domain=[d] --alias=[a]` — список таблиц и колонок алиаса (из information_schema). Возвращает `{tables: [{name, columns: [{name, type, nullable, key, default}]}]}`.
- `db:methods --domain=[d] --alias=[a] --provider=[p]` — список публичных методов провайдера (через Reflection).
- `db:describe --domain=[d] --alias=[a] --provider=[p] --method=[m]` — сигнатура: params, return type, summary.

### 11.2. Invocation (безопасный вызов)

- `db:call --domain=[d] --alias=[a] --provider=[p] --method=[m] --params='{}'` — read-only метод. Реализованные шаблоны: `select_where` (остальные — Phase 4.8, см. `v3.1-design.md` §4). Dry-run не требуется.
- `db:mutate --domain=[d] --alias=[a] --provider=[p] --method=[m] --params='{}' --force` — write-метод. Реализован шаблон `insert` (`update`/`delete` — Phase 4.8). **Требует `--force`.** Каждый вызов логируется в `audit_log` (`domain`, `alias_name`, `provider`, `method`, `params_json`, `result_ok`, `error_text`, `source=cli`, `role_name`).

### 11.3. Генерация методов (templates only)

- `dataprovider:add-method --domain=[d] --alias=[a] --provider=[p] --template=[t] --args='{}'` — генерирует метод из шаблона (реализованы `select_where`, `insert`; остальные — Phase 4.8). AI не пишет PHP — генерируется шаблон с PDO named params.

### 11.4. Роли (в `bin/aicli.php`, см. `v3.1-design.md` §6.2)

- `db:schema`, `db:methods`, `db:describe` — роль `inspector`.
- `db:call` — роли `inspector`, `executor`, `reporter`.
- `db:mutate` — роль `executor` (только с `--force`).
- `dataprovider:add-method` — роль `architect`.

**Запрещено всем ролям:** `CREATE/DROP/ALTER TABLE` (миграции — только `db:migrate` от владельца, не от AI), `TRUNCATE`, `GRANT/REVOKE`. Это инфорцируется в `Roles::whitelist()`.

---

## 12. Wizard (State Machine)


Пошаговая генерация сайта с проверкой стадий. Команды — **только `bin/aicli.php`** (в `bin/cli.php` wizard нет).
State Machine: `init → domain → allias → plan → content → skin → ready → committed` (`WizardSession::STAGE_*`).
Состояние — `var/wizard/` (см. `WizardSession::save()`); `session_id` — 32 hex-символа (regex `^[a-f0-9]{32}$`).

- `wizard:start` — создать сессию (без args). Возвращает `{session_id, stage: "init", next: "wizard:domain { session_id, domain }"}`. `next` — **строка-шаблон**, не HATEOAS-массив.
- `wizard:domain --session_id=[id] --domain=[fqdn]` — задать домен (init → domain). Regex FQDN, `..` отклоняется.
- `wizard:allias --session_id=[id] --allias=[name]` — задать алиас (domain → allias). Проверяет `app/{domain}/{allias}/` и `site.config.alliases_real` (не занят ли). Regex `^[a-z][a-z0-9_]*$`.
- `wizard:plan --session_id=[id] --pages='[...]'` — план страниц (allias → plan).
- `wizard:content --session_id=[id] --page=[name] --blocks='[...]'` — блоки одной страницы (plan|content → content).
- `wizard:skin --session_id=[id] --file=[css/main.css] --content="..."` — стили (content|skin → skin).
- `wizard:finish --session_id=[id]` — проверка контента, переход в ready (content|skin → ready).
- `wizard:commit --session_id=[id]` — запись в production (ready → committed).

**Скрытые / фантомные:**
- `wizard:widgets --session_id=[id] --widgets='[...]'` — есть в `WizardManager::handle()`, но **не в whitelist** ролей (`Roles.php`) — недоступна через `bin/aicli.php`.
- `wizard:abort` — есть в `Roles.php` (architect, `*`), но **нет `case` в `WizardManager`** → вернёт `Unknown wizard action: abort`.
- `wizard:step`, `wizard:gc` — были в предыдущей версии этого документа; в коде отсутствуют. Удалены.

---

## 13. AI-команды вне `bin/cli.php`

`wizard:*`, `agent:chat`, `ai:start`, `net:*`, `image:*`, `fetch` — только `bin/aicli.php`. См. `_doc/ai.md` §2 (роли), §5 (AINET), §6 (brain.py). `ai:edit-page` зарегистрирована в `bin/cli.php`, но role-gated — `_doc/ai.md` §2.
