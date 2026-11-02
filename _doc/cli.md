--- НАЧАЛО ФАЙЛА ---

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

- `page:new --domain=[d] --allias=[a] --name=[n] [--parent=...]` — создаёт 3 файла (`algoritm_[name].php`, `.rules.json`, `.editor.json`). Родитель должен существовать.
- `page:write --domain=[d] --allias=[a] --name=[n] --json='[...]'` — безопасная запись JSON. Флаги: `--json_b64`, `--data_file`, `--with_schema=1`. Для главной: `--name=__root__`.
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

--- КОНЕЦ ФАЙЛА ---