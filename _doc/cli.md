# CLI-команды

Точка входа: `php bin/cli.php [command] [--key=value]`. Возвращает `Result` (код 0 при успехе, 1 при ошибке). Полный список команд — `php bin/cli.php help` (54 команды).

**Принцип CLI-First:** CLI — единственный источник истины для файловых операций. Админка и AI-агенты (`bin/aicli.php`) делегируют работу ему.

⚠️ **Правила безопасности (действуют везде):**

- **Path Traversal Guard:** все команды с `--domain`/`--name` проверяют на `..` и regex. `--domain=a..ru` отклоняется. Regex домена — `/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/`; имени/алиаса/блока — `/^[a-z][a-z0-9_]*$/`; категории контента — `/^[a-z0-9_-]{1,32}$/`; hash — `/^[a-f0-9]{16}$/`.
- **Карантин (6 уровней):** все destructive-команды (`*:remove`, `trash:clear`) перемещают файлы в папку `*_trash_[timestamp]/`, а не удаляют сразу. Требуют флаг `--force`.
- **realDomain:** если `domains_real["alias.ru"] = "real.ru"`, пути в `app/` строятся по `real.ru`.
- **`--session=<tab>`** — изоляция AIUI. Принимают только `page:new` и `page:write` (см. §9). Остальные команды пишут в production.

---

## 1. Диагностика и миграции

- `help` — список всех команд.
- `structure:check` — проверка целостности (наличие 3 файлов, стыковка филлеров). Severity-aware: `error` (exit 1) и `warning` (exit 0).
- `structure:repair --domain=[d]` — восстанавливает отсутствующие `.php`, `.rules.json`, `.editor.json`.
- `migrate:page-rules [--domain=...] [--dry]` — идемпотентная миграция старых `.rules.json` на новую модель. `--dry` — предпросмотр.
- `db:migrate [--dir=<path>]` — применение SQL-миграций. Default — `migrations/` в корне проекта. `--dir` разрешает только подпапки `migrations/` (guard: `realpath` + `str_starts_with`).
- `trash:clear [--force] [--older=7d] [--domain=...]` — очистка карантина. По умолчанию dry-run. `--older` — формат `\d+[dhm]`. Обходит 6 уровней, использует `Fs::rmrf` (symlink-safe).

## 2. Домены и Алиасы

- `domain:new --domain=[fqdn] [--namespace=...]` — создаёт папку домена, алиас `www`, глобальную схему, root-страницу. `--namespace` опционален (default — `str_replace(['.','-'], '_', domain)`), regex `^[a-z][a-z0-9_]*$`.
- `domain:remove --domain=[fqdn] --force` — в карантин (`app/[d]_trash_[ts]/`). Очистка **16 ключей** `site.config`: `domains_real`, `domains_available`, `domains_namespace`, `domains_titles`, `domains_skins`, `domains_skins_allowed`, `domains_stubs`, `domains_scheme`, `alliases_real`, `alliases_available`, `alliases_langs`, `alliases_titles`, `alliases_stubs`, `alliases_skins`, `alliases_skins_allowed`, `fillers`. **Non-self-mapped домен удалить нельзя** (`domains_real[d] ≠ d` → `error`, укажите `--domain=<real>`). Idempotent.
- `domain:list` — список реальных доменов (без `''`).
- `allias:new --domain=[fqdn] --name=[allias]` — создаёт алиас (root-страницу через PageCompiler + дефолтную схему). Regex `^[a-z][a-z0-9_]*$`.
- `allias:remove --domain=[fqdn] --name=[allias] --force` — в карантин. Очистка **8 ключей** `site.config`: `alliases_real`, `alliases_available`, `alliases_langs`, `alliases_titles`, `alliases_stubs`, `alliases_skins`, `alliases_skins_allowed`, `fillers`. **Non-self-mapped домен** → `error` (как `domain:remove`).
- `allias:list --domain=[fqdn]` — список алиасов (группирует по real).

## 3. Страницы (Page)

- `page:new --domain=[d] --allias=[a] --name=[n] [--parent=...] [--session=tab]` — создаёт 3 файла (`algoritm_[name].php`, `.rules.json`, `.editor.json`). Родитель должен существовать. При `--session=tab` пишет в `app/.sess_{tab}.{domain}/`.
- `page:write --domain=[d] --allias=[a] --name=[n] --json='[...]' [--parent=...] [--session=tab] [--json_file=...] [--json_b64=...] [--with_schema=1]` — безопасная запись JSON. Три источника: `--json` (приоритет), `--json_file` (путь), `--json_b64` (base64). При `--with_schema=1` — оборачивает в схему. Для главной: `--name=__root__`. При `--session=tab` пишет в `app/.sess_{tab}.{domain}/` и сохраняет `.editor.json`.
- `page:read --domain=[d] --allias=[a] --name=[n] [--parent=...]` — вывод сгенерированного PHP-кода.
- `page:toggle --domain=[d] --allias=[a] --name=[n] --key=auth|search|default --value=1|0 [--parent=...]` — переключение флага в `.rules.json`.
- `page:list --domain=[d] --allias=[a]` — рекурсивный список страниц (с флагами).

## 4. Блоки и Филлеры

**Блоки** (в `.alliasglobal/`):

- `block:new --domain=[d] --name=[n] [--type=script|visual|mixed]` — создаёт 3 файла. `--type` default `mixed`.
- `block:write --domain=[d] --name=[n] --json='[...]' [--json_b64=...]` — безопасная запись JSON-контента в блок (компилируется через `BlockCompiler`).
- `block:edit --domain=[d] --name=[n] --file=[php|rules|editor] [--content="..."] [--content_b64=...] [--allow_php=1]` — правка `.php` / `.rules.json` / `.editor.json`. `--allow_php` — RCE-guard: разрешён только если `engine.config.security.allow_php_blocks=true`.
- `block:remove --domain=[d] --name=[n] --force` — в карантин.
- `block:list --domain=[d]` — список глобальных блоков.

**Филлеры** (переопределения блоков для алиаса):

- `filler:new --domain=[d] --allias=[a] --block=[b]` — создаёт 3 файла `[block]_[allias].*`.
- `filler:write --domain=[d] --allias=[a] --block=[b] --json='[...]' [--json_b64=...]` — безопасная запись JSON-контента в филлер.
- `filler:toggle --domain=[d] --allias=[a] --block=[b] [--value=1|0]` — idempotent. `--value` default `1`. Проверяет блок на диске.
- `filler:edit --domain=[d] --allias=[a] --block=[b] --file=[php|rules|editor] [--content="..."] [--content_b64=...] [--allow_php=1]` — правка правил.
- `filler:list --domain=[d] --allias=[a]` — список филлеров.

## 5. Схемы (Scheme)

- `scheme:new --domain=[d] --name=[n]` — создаёт 3 файла в `.alliasscheme/` с базовыми meta (shared CSS/JS).
- `scheme:copy --domain=[d] --name=[n]` — копирует в `[n]_copy` (или `[n]_copy_1`).
- `scheme:save --domain=[d] --name=[n] --scheme_json='...' [--scheme_json_b64=...]` — сохраняет визуальную схему (обновляет `.editor.json`, компилирует `.php`). Принимает массив `children` (слоты) и `meta` (базовые CSS/JS).
- `scheme:remove --domain=[d] --name=[n] --force` — в карантин.
- `scheme:list --domain=[d]` — список схем.

## 6. Контент (Content — `/content/`)

- `content:store --domain=[d] --allias=[a] --category=[c] (--data_b64=[b] | --data_file=[path]) --mime=image/png [--alt=...] [--source_url=...]` — запись байтов (вызывается из `aicli.php`). `--category` default `general`. Приоритет `--data_file` над `--data_b64`. Валидация: MIME, ≤ 10 MB, ≥ 200×200. Дедупликация по hash.
- `content:remove --domain=[d] --allias=[a] --category=[c] --hash=[h] --force` — в карантин (`/content/.trash/`).
- `content:list --domain=[d] [--allias=...] [--category=...]` — список (mime, size, page_refs).
- `content:gc [--force] [--older=...] [--domain=...] [--dry]` — поиск orphan-контента и отправка в карантин. Без `--force`/`--dry` — ошибка.

## 7. Скины (Skin — `www/media/.../skin/`)

- `skin:new --domain=[d] --allias=[a] [--name=default]` — создаёт `css/main.css`, `js/main.js`, `img/`, `skin.json`. `--name` default `default`.
- `skin:edit --domain=[d] --allias=[a] --name=[n] --file=[file] [--content="..."] [--content_b64=...]` — редактирует CSS (`css/*.css`, например `css/main.css`, `css/top.css`) и `js/main.js`. Защита: размер ≤ 1 MB, блокировка `@import`/`javascript:`.
- `skin:remove --domain=[d] --allias=[a] --name=[n] --force` — в карантин (`www/media/.trash/`).
- `skin:list --domain=[d] --allias=[a]` — список скинов.

## 8. Пользователи и Безопасность

- `admin:passwd [--name=admin] [--password=...]` — установка логина/пароля админки. `--name` default `admin`. Валидация: login 3–32 `[a-zA-Z0-9_.-]`, pass ≥ 8.
- `user:new --login=[login] --email=[email] --password=[pass]` — создание пользователя сайта (для страниц с `auth`). Уникальность login и email, pass ≥ 8.

## 9. AIUI Сессии (Session-based workflow)

Команды для изолированной работы AIUI (`www/admin/ai_edit.php`). Все работают с `app/.sess_{tab}.{domain}/` и `www/media/.sess_{tab}.{domain}/`, не трогая production. `tab` — 32 hex (`^[a-f0-9]{8,32}$`).

- `session:start --domain=[d] --tab=[hex] [--admin-sess=login]` — копирует `app/{domain}/` и `www/media/{domain}/` в `.sess_{tab}`. Пишет `.session.json` с permissions.
- `session:apply --domain=[d] --tab=[hex] [--admin-sess=login]` — атомарно переносит `.sess_{tab}` в production (optimistic lock по `production_hash`). Старая папка → `_trash_sess_*`.
- `session:discard --domain=[d] --tab=[hex]` — переносит `.sess_{tab}` в карантин.
- `session:list` — сканирует `app/.sess_*`, читает meta.

**Флаг `--session=tab`** — принимают только `page:new` и `page:write` (по коду: `grep -rln "args\['session'\]" src/Cli/Commands/`). Для остальных write-команд (`block:write`, `filler:write`, `skin:edit`, `scheme:save`) session-изоляция **не реализована** — они пишут в production. Это известный разрыв, не пытайтесь использовать их в AIUI без понимания.

## 10. DB & DataProvider (read-only + safe write)

### 10.1. Introspection (read-only)

- `db:schema --domain=[d] [--alias=www]` — список таблиц и колонок алиаса (из `information_schema`).
- `db:methods --domain=[d] [--alias=www] --provider=[p]` — список публичных методов провайдера (через Reflection).
- `db:describe --domain=[d] [--alias=www] --provider=[p] --method=[m]` — сигнатура: params, return type, summary.

### 10.2. Invocation (безопасный вызов)

- `db:call --domain=[d] [--alias=www] --provider=[p] --method=[m] [--params='{}' | --params_b64=...]` — read-only метод. Реализованный шаблон: `select_where`. Dry-run не требуется.
- `db:mutate --domain=[d] [--alias=www] --provider=[p] --method=[m] --params='{}' --force=1 [--params_b64=...] [--source=cli] [--role=...]` — write-метод. Реализованный шаблон: `insert`. **Требует `--force=1`.** Каждый вызов логируется в `audit_log` (`domain`, `alias_name`, `provider`, `method`, `params_json`, `result_ok`, `error_text`, `source`, `role_name`).

### 10.3. Генерация методов (templates only)

- `dataprovider:add-method --domain=[d] [--alias=www] --provider=[p] --template=[t] [--args='{}' | --args_b64=...]` — генерирует метод из шаблона. Реализованы `select_where`, `insert` (`src/Db/DataProvider/Template/`). AI не пишет PHP — генерируется шаблон с PDO named params.

### 10.4. Роли (в `bin/aicli.php`, см. `ai.md` §2)

- `db:schema`, `db:methods`, `db:describe` — роль `inspector`.
- `db:call` — роли `inspector`, `executor`, `reporter`.
- `db:mutate` — роль `executor` (только с `--force`).
- `dataprovider:add-method` — роль `architect`.

**Запрещено всем ролям:** `CREATE/DROP/ALTER TABLE` (миграции — только `db:migrate` от владельца, не от AI), `TRUNCATE`, `GRANT/REVOKE`. Инфорцируется в `Roles::whitelist()`.

## 11. `ai:edit-page` (admin-only)

- `ai:edit-page --domain=[d] [--alias=www] [--page=__root__] --prompt="..." [--force=1]` — редактирование страницы через LLM. **Не в `Roles::whitelist()`** — недоступна через `bin/aicli.php`. В CLI-команде admin-check (ADMINSESSID) отсутствует; вызов предполагает запуск от владельца (shell) или из `ai_edit.php` (там auth-проверка до вызова). Без `--force=1` — dry-run (diff блоков, без сохранения). См. `aiui.md`.

## 12. Чего нет в `bin/cli.php` (только через `bin/aicli.php`)

Следующие команды **не зарегистрированы** в `bin/cli.php` — они живут только в `bin/aicli.php` (JSON-in/out, role-gated). Описание — `ai.md`:

- `wizard:*` (9 actions: `start`, `domain`, `allias`, `plan`, `content`, `skin`, `finish`, `commit`, `abort` — см. `ai.md` §7)
- `agent:chat`, `ai:start`, `done`
- `net:status`, `image:search`, `image:download`, `fetch` (AINET — `ai.md` §5)

**Известные полуреализации** (заявлены в `Roles.php`, но `case` отсутствует / нет в whitelist):

- `wizard:abort` — в `Roles.php` есть (architect, `*`), `case` в `WizardManager::handle()` нет → `Unknown wizard action`.
- `wizard:widgets` — реализован (`WizardManager.php:45`, `setWidgets`), но отсутствует в `Roles.php` → недоступен через aicli.
- `--session` для `block:write`, `filler:write`, `skin:edit`, `scheme:save` — не реализован (см. §9).
