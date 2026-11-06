# CLI-команды

Точка входа: `php bin/cli.php [command] [--key=value]`. Возвращает `Result` (код 0 при успехе, 1 при ошибке).

**Принцип CLI-First:** CLI — единственный источник истины для файловых операций. Админка и AI-агенты (`bin/aicli.php`) делегируют работу ему.

⚠️ **Правила безопасности:**
- **Path Traversal Guard:** все команды с `--domain`/`--name` проверяют на `..` и regex. `--domain=a..ru` отклоняется.
- **Карантин (6 уровней):** все destructive-команды (`*:remove`, `trash:clear`) перемещают файлы в папку `*_trash_[timestamp]/`, а не удаляют сразу. Требуют флаг `--force`.
- **realDomain:** если `domains_real["alias.ru"] = "real.ru"`, пути в `app/` строятся по `real.ru`.

---

## 1. Диагностика и миграции

- `help` — список всех команд (актуальный состав — по выводу самой команды).
- `structure:check` — проверка целостности (наличие 3 файлов, стыковка филлеров). Severity-aware: `error` (exit 1) и `warning` (exit 0).
- `structure:repair --domain=[domain]` — восстанавливает отсутствующие `.php`, `.rules.json`, `.editor.json`.
- `migrate:page-rules [--domain=...] [--dry]` — идемпотентная миграция старых `.rules.json` на новую модель.
- `db:migrate [--dir=migrations/archive]` — применение SQL-миграций (только подпапки `migrations/`).
- `trash:clear [--force] [--older=7d] [--domain=...]` — очистка карантина. По умолчанию dry-run. Обходит 6 уровней, использует `Fs::rmrf` (symlink-safe).

## 2. Домены и Алиасы

- `domain:new --domain=[fqdn]` — создаёт папку домена, алиас `www`, глобальную схему, root-страницу.
- `domain:remove --domain=[fqdn] --force` — в карантин. Полная очистка конфига (16 ключей). Idempotent.
- `domain:list` — список реальных доменов (без `''`).
- `allias:new --domain=[fqdn] --name=[allias]` — создаёт алиас (root-страницу через PageCompiler + дефолтную схему).
- `allias:remove --domain=[fqdn] --name=[allias] --force` — в карантин. Очистка конфига (8 ключей).
- `allias:list --domain=[fqdn]` — список алиасов (группирует по real).

## 3. Страницы (Page)

- `page:new --domain=[d] --allias=[a] --name=[n] [--parent=...] [--session=tab]` — создаёт 3 файла (`algoritm_[name].php`, `.rules.json`, `.editor.json`). Родитель должен существовать. При `--session=tab` пишет в `app/.sess_{tab}.{domain}/`.
- `page:write --domain=[d] --allias=[a] --name=[n] --json='[...]' [--session=tab]` — безопасная запись JSON. Флаги: `--json_b64`, `--json_file`, `--data_file`, `--with_schema=1`. Для главной: `--name=__root__`. При `--session=tab` пишет в `app/.sess_{tab}.{domain}/` и сохраняет `.editor.json`.
- `page:read --domain=[d] --allias=[a] --name=[n]` — вывод сгенерированного PHP-кода.
- `page:toggle --domain=[d] --allias=[a] --name=[n] --key=auth|search|default --value=1|0` — переключение флага в `.rules.json`.
- `page:list --domain=[d] --allias=[a] [--json]` — рекурсивный список страниц (с флагами).

## 4. Блоки и Филлеры

- `block:new --domain=[d] --name=[n] --type=script|visual|mixed` — создаёт 3 файла в `.alliasglobal/`.
- `block:write --domain=[d] --name=[n] --json='[...]'` — безопасная запись JSON-контента в блок (компилируется через BlockCompiler).
- `block:edit --domain=[d] --name=[n] --json='...'` — правка `.rules.json` / `.editor.json` (PHP не трогает, защита от RCE).
- `block:remove --domain=[d] --name=[n] --force` — в карантин.
- `block:list --domain=[d]` — список глобальных блоков.
- `filler:new --domain=[d] --allias=[a] --block=[b]` — создаёт 3 файла `[block]_[allias].*`.
- `filler:write --domain=[d] --allias=[a] --block=[b] --json='[...]'` — безопасная запись JSON-контента в филлер.
- `filler:toggle --domain=[d] --allias=[a] --block=[b] --value=1|0` — idempotent. Проверяет блок на диске.
- `filler:edit --domain=[d] --allias=[a] --block=[b] --json='...'` — правка правил.
- `filler:list --domain=[d] --allias=[a]` — список филлеров.

## 5. Схемы (Scheme)

- `scheme:new --domain=[d] --name=[n]` — создаёт 3 файла в `.alliasscheme/` с базовыми meta (shared CSS/JS).
- `scheme:copy --domain=[d] --name=[n]` — копирует в `[n]_copy` (или `[n]_copy_1`).
- `scheme:remove --domain=[d] --name=[n] --force` — в карантин.
- `scheme:save --domain=[d] --name=[n] --scheme_json='...'` — сохраняет визуальную схему (обновляет `.editor.json`, компилирует `.php`). Принимает массив `children` (слоты) и `meta` (базовые CSS/JS). Флаг `--scheme_json_b64` для AI.
- `scheme:list --domain=[d]` — список схем.

## 6. Контент (Content — `/content/`)

- `content:store --domain=[d] --allias=[a] --category=[c] --data_b64=[b] --mime=image/png [--alt=...]` — запись байтов (вызывается из `aicli.php`). Валидация: MIME, ≤ 10 MB, ≥ 200×200. Дедупликация по hash.
- `content:remove --domain=[d] --allias=[a] --category=[c] --hash=[h] --force` — в карантин (`/content/.trash/`).
- `content:list --domain=[d] [--allias=...] [--category=...]` — список (mime, size, page_refs).
- `content:gc [--force] [--older=...] [--domain=...]` — поиск orphan-контента и отправка в карантин.

## 7. Скины (Skin — `www/media/.../skin/`)

- `skin:new --domain=[d] --allias=[a] --name=[n]` — создаёт `css/main.css`, `js/main.js`, `img/`, `skin.json`.
- `skin:edit --domain=[d] --allias=[a] --name=[n] --file=[file] --content="..."` — редактирует любые CSS-файлы (`css/*.css`, например `css/main.css`, `css/top.css`) и `js/main.js`. Защита: размер ≤ 1 MB, блокировка `@import`/`javascript:`.
- `skin:remove --domain=[d] --allias=[a] --name=[n] --force` — в карантин (`www/media/.trash/`).
- `skin:list --domain=[d] --allias=[a]` — список скинов.

## 8. Пользователи и Безопасность

- `admin:passwd --name=[login] --password=[pass]` — установка логина/пароля админки. Валидация: login 3-32 `[a-zA-Z0-9_.-]`, pass ≥ 8.
- `user:new --login=[login] --email=[email] --password=[pass]` — создание пользователя сайта (для страниц с `auth`). Уникальность login и email, pass ≥ 8.

---

## 9. Чего в CLI НЕТ (только через UI админки)

- CodeMirror-редактор (правка PHP-кода).
- Визуальный Drag&Drop для Схем (сохраняется через `scheme:save`).
- Управление банами (rate limit).
- Просмотр логов (`var/admin_logs.json`).

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

- `db:call --domain=[d] --alias=[a] --provider=[p] --method=[m] --params='{}'` — read-only метод (whitelist шаблонов `select_by`, `select_where`, `select_all`, `count`, `exists`, `aggregate`, `join_select`, `group_by_count`, `sum_by`). Dry-run не требуется.
- `db:mutate --domain=[d] --alias=[a] --provider=[p] --method=[m] --params='{}' --force` — write-метод (`insert`, `update`, `delete`). **Требует `--force`.** Каждый вызов логируется в `audit_log` (`domain`, `alias_name`, `provider`, `method`, `params_json`, `result_ok`, `error_text`, `source=cli`, `role_name`).

### 11.3. Генерация методов (templates only)

- `dataprovider:add-method --domain=[d] --alias=[a] --provider=[p] --template=[t] --args='{}'` — генерирует метод из шаблона (шаблоны: см. `v3.1-design.md` §4, реализован `select_where`). AI не пишет PHP — генерируется шаблон с PDO named params.

### 11.4. Роли (в `bin/aicli.php`, см. `v3.1-design.md` §6.2)

- `db:schema`, `db:methods`, `db:describe` — роль `inspector`.
- `db:call` — роли `inspector`, `executor`, `reporter`.
- `db:mutate` — роль `executor` (только с `--force`).
- `dataprovider:add-method` — роль `architect`.

**Запрещено всем ролям:** `CREATE/DROP/ALTER TABLE` (миграции — только `db:migrate` от владельца, не от AI), `TRUNCATE`, `GRANT/REVOKE`. Это инфорцируется в `Roles::whitelist()`.

---

## 12. Wizard и AI-команды

Внутренние AI-команды (используются `ai_agent/brain.py` и администратором). Низкоуровневый JSON-протокол — `ai.md` §1-3.

### 12.1. Wizard (State Machine)

Пошаговая генерация сайта с проверкой стадий. Состояние — в `var/wizard/{domain}/{alias}/{session_id}.json` (TTL 24h).

- `wizard:start --domain=[d] --alias=[a]` — создать сессию. Возвращает `session_id`, `stage: init`, `next[]` (HATEOAS).
- `wizard:step --session=[id] --command=[cmd] --args='{}'` — выполнить шаг (команда из `next[]`). Валидирует, что не пропущена стадия.
- `wizard:gc [--older=24h]` — очистка старых сессий в `var/wizard/_trash_{ts}/`.

### 12.2. AI-сессии

- `ai:start --role=[r]` — инициализация AI-сессии. Возвращает `role`, `next[]` (Navigator).
- `agent:chat --history='[...]' --model=[m] [--system_prompt=...]` — низкоуровневый вызов LLM (используется `InterviewerAgent`/`CoderAgent` через `AgentChatHandler`, а также `brain.py`). Возвращает `{reply: string}`.

### 12.3. Admin-only

- `ai:edit-page --domain=[d] --alias=[a] --page=[p] --prompt="..." [--force=1]` — редактирование страницы через LLM. **Только для администратора** (не доступна через `bin/aicli.php`). Без `--force` — dry-run (diff блоков, без сохранения). См. `_doc/aiui.md`.

### 12.4. AINET (сеть)

- `net:status` — диагностика провайдеров, лимитов, кэша.
- `image:search --query="..." --count=5` — поиск картинок (провайдеры: `wikimedia`, `openverse`).
- `image:download --url="..." --domain=[d] --alias=[a] --category=[c]` — скачать в `/content/`. Возвращает `local_url`.
- `fetch --url="..." --type=json|text` — GET-запрос к whitelist-хосту (`config/ainet.config`).

Полное описание — `_doc/ai.md` §5.
