Архитектура Damoony Engine

Движок построен по принципам Explicit over implicit, Contract-first и CLI-First. Ниже — жизненный цикл HTTP-запроса, слои системы и принципы интеграции с AI. Термины — по MANIFEST.md §2. Расхождения с кодом — по MANIFEST.md §6–7.

---

## Точки входа

- `www/index.php` — Front Controller для публичного сайта.
- `www/admin/index.php` — Front Controller админки (обёрнут в try/catch для Safe Mode).
- `www/content.php` — Front Controller для runtime-контента (`/content/`). GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`.
- `bin/cli.php` — точка входа CLI (Единый источник истины для файловых операций).
- `bin/aicli.php` — единственный безопасный фасад (API) для AI-агентов.
- `bin/ainet.php` — standalone-отладка AINET (не для AI).

Все точки входа инициализируют контейнер (PSR-11), окружение (Env) и ядро (Kernel).

---

## Жизненный цикл HTTP-запроса

**Kernel** — принимает `Request`, оборачивает выполнение в try/catch, логирует ошибки, возвращает `Response`. Гарантирует: белых экранов нет.

**Middleware** — стек: `SecurityHeadersMiddleware` (CSP, HSTS), `RateLimitMiddleware` (брутфорс), `LanguageCookieMiddleware`, `SkinCookieMiddleware`.

**FrontController:**

1. `DomainResolver::resolve(host)` — определяет `DomainContext`. Нормализует Host (отрезает порт, trailing-dot).
2. Проверяет доступность домена и алиаса. Если `false` — 503 из `stubs/`.
3. `Router::match(path, domain)` → `RouteMatch`. Проверяет флаг `auth`.
4. Создаёт `RegionContext` и передаёт в `PageHandler`.

**Router (FileRouter)** — идёт по дереву `algoritm/`. На каждом шаге читает правила текущего класса:

- root: `algoritm/algoritm.rules.json`
- уровень N: `algoritm/<pathRel>/algoritm_<pathRel_underscores>.rules.json`

Возвращает `RouteMatch`.

**PageHandler** — загружает `RegionRegistry` (через `RegionLoader`), подключает PHP-файл страницы, вызывает `default()`. Если страница возвращает `null`, PageHandler возвращает 204 No Content. Если бросает исключение — в dev отдаёт сообщение, в prod — generic 500.

Важно: PageHandler **НЕ** оборачивает Node в Document через схему автоматически. Если страница вернула Node, он кладётся в `<body>` как есть — без шапки/футера/регионов. Разработчик САМ обязан вызвать `SchemeLoader` + `SchemeRenderer` в коде страницы (Вариант A из `guide.md`), либо использовать AI-команду `page:write --with_schema=1`.

---

## Слой домена и алиаса

Домен — папка `app/<real_domain>/`. Входящие хосты мапятся на реальный домен через `ConfigDomainResolver`.
Алиас — папка внутри домена (`www`, `doc`).
Инфраструктурные папки скрыты точкой: `.alliasglobal/` (блоки), `.alliasscheme/` (схемы).

**realDomain:** все пути в `app/` строятся по `realDomain` из `site.config` (`domains_real[<host>] = <real>`), а не по входному хосту. Если `domains_real["damoony3.ru"] = "damoony.ru"`, то папка будет `app/damoony.ru/`. Все CLI-команды (`block:new/remove`, `filler:new/toggle`, `scheme:*`, `skin:*`, `structure:repair`) резолвят `realDomain` перед построением пути.

---

## Правило 3 файлов (Архитектурный стандарт)

Каждый логический элемент (Страница, Блок, Филлер, Схема, Скин) строго описывается тремя файлами:

1. `{name}.php` — PHP-код (класс или массив).
2. `{name}.rules.json` — Настройки (тип блока, `auth`, `search`, `available`, `enabled`).
3. `{name}.editor.json` — Данные для UI (деревья вложенности).

Для страниц все три файла имеют единый префикс:

- root: `algoritm/algoritm.php` + `algoritm.rules.json` + `algoritm.editor.json`
- `/about/`: `algoritm/about/algoritm_about.php` + `algoritm_about.rules.json` + `algoritm_about.editor.json`
- `/docs/install/`: `algoritm/docs/install/algoritm_docs_install.php` + `algoritm_docs_install.rules.json` + `algoritm_docs_install.editor.json`

Глобальные манифесты (`blocks.json`, `schemes.json`) удалены. Источник истины — локальные файлы.

Старая модель (`algoritm/.rules.json` для root, `{dir}/.rules.json` для вложенных) мигрируется командой `migrate:page-rules`. Эта команда идемпотентна, шаги: (1) root, (2) подпапки, (3) нормализация полей, (4) создание пустых `.editor.json`.

---

## Page-слой (резолвинг путей и правил)

Единый набор хелперов, используемый всеми page-командами (`new/write/read/toggle`):

- `PagePath::resolve(Paths, domain, allias, name, parent): PagePath` — возвращает `targetRelPath`, `classChain`, `pageDir`, `phpFile`, `rulesFile`, `editorFile`, а также `parentDir`, `parentPhpFile`, `parentRulesFile`.
- `PageRules::load/save/defaults/withDefaults/withChild` — единый источник `.rules.json` (поля `available/auth/search/default`).
- `PageNamespace::fromSite(site, domain): string` — резолв PHP-namespace из `site.config` (fallback: `str_replace('.', '_', domain)`).

Назначение: устранить дублирование формул путей/класса между 4 командами, обеспечить единый формат правил.

---

## Слой контента и скинов

### Content (`/content/`)

Runtime-хранилище AI-контента, вне webroot. Отдаётся через `www/content.php`.

```text
/content/
├── .trash/                         # карантин (runtime, .gitignore)
└── <domain>/<allias>/<category>/   # AI-контент (runtime, .gitignore)
    ├── <hash>.<ext>
    └── <hash>.meta.json
```

Файл: `<hash>.<ext>` (sha256 содержимого, первые 16 символов) + `<hash>.meta.json` (`source_url`, `alt`, `size`, `mime`, `width`, `height`, `page_refs`, `hash`, `downloaded_at`).

Дедупликация по hash: повторная запись того же содержимого возвращает существующий путь.
Запись — **только** через `cli.php content:store` (единственный источник истины для FS). AINET не пишет на диск.

**Отдача (`www/content.php`):**

- GET/HEAD only (POST/PUT/DELETE → 405).
- `ContentResponder::parse(uri)` → `{domain, allias, category, hash, ext}`.
- `ContentResponder::hostAllowed(domain, host)` — не палим чужие домены.
- `ContentResponder::requiresAuth(req, meta, paths)` — auth-check по `page_refs` в `meta.json`.
- Headers: `Content-Type`, `Cache-Control: public, max-age=31536000, immutable`, `ETag`, `Last-Modified`, `X-Content-Type-Options: nosniff`.
- 304 Not Modified при совпадении `If-None-Match`.
- SVG: `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`.

### Skin (`www/media/<domain>/<allias>/skin/`)

Git-tracked инфраструктура. Управление — только через CLI `skin:new/list/edit/remove`.

```text
www/media/<domain>/<allias>/skin/<name>/
├── skin.json          # {name, created_at, ...}
├── css/main.css
├── js/main.js
└── img/
```

`skin:edit` — только `css/main.css` и `js/main.js`, размер ≤ 1 MB, блок `@import` и `javascript:` URL (защита от inline-инъекций).

### Три зоны `www/media/` (git) vs `/content/` (runtime)

```text
www/media/
├── admin/                          # инфраструктура (git, руками)
├── shared/                         # инфраструктура (git, руками)
├── .trash/                         # карантин skin:remove (runtime, .gitignore)
└── <domain>/<allias>/skin/         # скины (git, руками)
```

`/content/` — runtime, `.gitignore`. Отдельный front controller. Не смешивается с `www/media/`.

---

## Слой UI и Компоненты

UI — типизированное дерево (не строки): `Node`, `Element`, `Attrs`, `Builder`, `Document`, `Meta`.
`Attrs::release()` экранирует значения через `htmlspecialchars`. Имена атрибутов валидируются (XSS закрыт).
`Document::release()` идемпотентен — повторный вызов не дублирует теги в `<head>`.

---

## Регионы, Слоты и Схемы

**Region** (`RegionInterface`): независимый блок контента (шапка, футер). Настройки типа (`visual`, `script`, `mixed`) читаются из `{name}.rules.json`.
**Slot**: узел в схеме для вставки региона. Если слот не заменён регионом, он молча исчезает.
**Scheme**: `SchemeRenderer` собирает каркас из манифеста и слотов.
**Filler**: переопределение региона для конкретного алиаса (`top_www.php`). Приоритет: `disabled` → `forcedGlobal` → `filler` → `base`.

---

## Low-Code и Компиляция (Zero-Overhead)

Механика: визуальный редактор (Drag&Drop) оставлен ТОЛЬКО для Схем. Админка сохраняет структуру схемы в `scheme.editor.json`, а `SchemeCompiler` компилирует её в `{name}.php` через CLI `scheme:save`.

Страницы, Блоки и Филлеры: визуальные редакторы удалены (закрытие RCE).

**Безопасная компиляция контента (PageCompiler):**
Для AI и автоматизации внедрен безопасный PageCompiler. AI передает JSON-массив блоков (`h1`, `text`, `img` и т.д.) через команду `page:write`. PageCompiler конвертирует этот JSON в нативный PHP-код (через Builder), используя `var_export` для строк. Это исключает RCE (AI не может написать сырой PHP, такой как `system()`).

`PageCompiler::compile(namespace, classChain, blocks, withSchema = false)`:

- `withSchema = true` — генерирует код, который оборачивает контент в схему (`SchemeLoader` + `SchemeRenderer`). Используется через `page:write --with_schema=1`.
- `withSchema = false` — «голая» страница (для API, AJAX).

PageCompiler также используется в `domain:new`, `allias:new`, `structure:repair` для генерации stub-страницы (единый шаблон класса).

**RegionCompiler:** используется для компиляции блоков. Для генерации сырого PHP (`custom/if`) требуется включить `security.allow_php_blocks=true` в `engine.config`. По умолчанию заблокировано.

---

## Слой данных

Только PDO (`Connection`). SQLite (тесты) / MariaDB (prod).
`MigrationRunner` применяет `.sql` из `migrations/`, идемпотентен.
`db:migrate`: `--dir` ограничен подпапками `migrations/` (path traversal guard через realpath).

---

## Авторизация

`DbAuth` (`password_hash` / `password_verify`).
Флаг `auth` в `algoritm_{name}.rules.json` проверяется FrontController → `Response::redirect('/login?return=...', 302)`.
Админка — отдельная авторизация (`config/admin.config`). Все POST — CSRF.
`admin:passwd` — валидация login (3-32 `[a-zA-Z0-9_.-]`) и password (≥8).
`user:new` — валидация email, уникальность login и email, password ≥8.

---

## CLI и Админка

**CLI: 40 команд**, возвращают `Result` (`ok`, `errors`), идемпотентны.

- app/ (27): `domain:*`, `allias:*`, `block:*` (с `edit`), `filler:*` (с `edit`), `page:*`, `scheme:*`, `db:migrate`, `admin:passwd`, `user:new`, `trash:clear`, `structure:check`, `structure:repair`, `migrate:page-rules`, `help`.
- `*:list` (8): `domain:list`, `allias:list`, `page:list`, `scheme:list`, `block:list`, `filler:list`, `content:list`, `skin:list` (с `--json` и `Result::$data`).
- content/ (3): `content:store`, `content:remove`, `content:list`.
- skin/ (4): `skin:new`, `skin:list`, `skin:edit`, `skin:remove`.
- Диагностика: `structure:check` (severity-aware), `structure:repair`, `migrate:page-rules`.
- Destructive: `domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `content:remove`, `skin:remove`, `trash:clear` — все в карантин (`*_trash_<timestamp>/`), требуют `--force`.
- Карантин: 6 уровней (`app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`). Очистка — `trash:clear` с флагами `--force/--older/--domain`. Symlink-safe `Fs::rmrf`.
- Path traversal guard: все команды с `--domain`/`--name` проверяют `..` и regex.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- Content: дедупликация по hash (`content:store`).
- Skin: `skin:edit` — только 2 файла, 1 MB, блок `@import`/`javascript:`.

**Admin:** Тонкий клиент. Контроллеры занимаются только рендерингом HTML и валидацией форм. Вся файловая логика делегируется вызовом CLI-команд, включая `scheme:copy/remove/save` и `content:*`. CodeMirror для страниц/блоков. Визуальный Drag&Drop только для Схем. POST защищены CSRF.
Админка и CLI делят бизнес-логику.

---

## Диагностика

StructureChecker проверяет:

- наличие всех 3 файлов для каждой страницы (`algoritm_{name}.{php,rules.json,editor.json}`);
- наличие `algoritm/algoritm.rules.json` (root);
- устаревшие `{dir}/.rules.json` (warning → `migrate:page-rules`);
- стыковку филлер↔слот;
- наличие схем и их `.rules.json`;
- наличие `.php/.rules.json/.editor.json` для блоков.

**Severity:**

- `error`: отсутствует `.php`, отсутствует `algoritm/algoritm.rules.json`, отсутствует схема.
- `warning`: отсутствует `.editor.json`, отсутствует `.rules.json` у блока/схемы, устаревший `.rules.json`, филлер без слота.

`StructureCheckCommand`: `$r->ok = true`, если нет errors. Warnings отображаются через `⚠` и не валят команду (exit 0). При errors — `✗` и exit 1.

`StructureRepairCommand`: восстанавливает отсутствующие root-страницы (`algoritm.php` через PageCompiler, `algoritm.rules.json`, `algoritm.editor.json`), `algoritm.rules.json` для схем, `{name}.rules.json/.editor.json` для блоков. Path traversal guard на domain.

---

## AI-Слой (Agent Integration)

Единственная точка входа для AI — `bin/aicli.php`. AINET — библиотека, не отдельный фасад.

**Архитектура:**

```text
[AI-Модель (Ollama, Qwen)]
      ↕ (JSON-план)
[Python Скрипт (agent_test.py, agentic loop)]
      ↕ (JSON через STDIN)
[bin/aicli.php (Единственный AI-фасад)]
      │
      ├── Roles (9 ролей) + Navigator (next[] HATEOAS)
      │       └── whitelist '*' = union всех ролей + специфичные
      │
      ├── app/* команды (page:*, domain:*, allias:*, scheme:*, block:*, filler:*, skin:*)
      │       └── shell_exec → bin/cli.php → FS (app/ + www/media/.../skin/)
      │
      ├── content:store (только внутренний вызов из image:download)
      │       └── shell_exec → bin/cli.php → FS (/content/)
      │
      └── net:* (net:status, fetch, image:search, image:download)
              ├── fetch, image:search, net:status → src/Ainet/* (in-process) → Интернет
              └── image:download → AinetFacade (bytes) → shell_exec → cli.php content:store → FS
```

**Разделение ответственности:**

- **bin/aicli.php** — единственная точка входа для AI. Roles, Navigator, валидация, диспетчеризация. Не содержит бизнес-логику AINET/CLI. Не пишет на диск.
- **src/Ai/Roles.php** — 9 ролей, whitelist команд по роли. Роль `*` (default) = union всех ролей + специфичные.
- **src/Ai/Navigator.php** — `next[]` (HATEOAS), `OFFERABLE = [domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, done]`. `next[]` фильтруется по whitelist роли.
- **src/Ainet/** — сеть: SSRF (UrlGuard), whitelist (DomainWhitelist), HTTP (HttpClient, CurlTransport), кэш (Cache), провайдеры (ImageSearch), публичный API (AinetFacade). **Не пишет на диск.**
- **bin/cli.php** — FS: единственный источник истины для записи/удаления/списка. Все `content:*` и `skin:*` операции.
- **bin/ainet.php** — standalone-отладка (не в AI-whitelist).

**Безопасность AI-слоя:**

- Агент не имеет доступа к shell напрямую. Все вызовы — через whitelist.
- Жесткая валидация аргументов (regex): `domain`, `allias`, `name`, `block`, `category`.
- Роли (9): `parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter`. Источник — `src/Ai/Roles.php`.
- Роль `*` (default, для человека) = union всех ролей + специфичные (`read_test_file`, `filler_toggle`, `net:*`, `skin:remove`, `*:list`). Роль `*` — надмножество всех ролей (инвариант, покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`).
- Запрещены всем ролям: `user:new`, `db:migrate`, `admin:passwd`, `domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `trash:clear`, `migrate:page-rules`, `content:*` (запись только через `image:download`).
- Два whitelist доменов: `config/agent.config` (`allowed_domains` для `app/*` и `image:download.domain`) + `config/ainet.config` (`allowed_domains` для URL `net:*`).
- Изоляция доменов: AI может создавать только домены из `agent.config`.
- Спец. ключ `__root__` — для записи на главную страницу аллиаса. `--parent` с `__root__` — ошибка.
- `*:list` (6 команд: `domain:list`, `allias:list`, `page:list`, `scheme:list`, `block:list`, `filler:list`) — инспекция состояния через `Result::$data` и `--json`.

**AINET (SSRF-защита, no RCE):**

- UrlGuard: scheme ∈ {http, https}, port ∈ {80, 443}, host в whitelist, private IP block (127/8, 10/8, 172.16/12, 192.168/16, 169.254/16, ::1, fc00::/7, fe80::/10), no `user:pass`, query ≤ 2048.
- HttpClient: GET-only, ручные редиректы (`CURLOPT_FOLLOWLOCATION=false`) → повторный UrlGuard на каждый Location.
- Cache: `sha256(url)` → file+meta, TTL 7 дней.
- Rate limit: 30 req/min, **enforced** в `AinetFacade` (`RateLimiter`, `var/ainet-ratelimit.json`, fixed-window, flock).
- Провайдеры: wikimedia (default, без ключа), openverse (fallback, без ключа). Автофоллбэк primary → next (только для default provider).

---

## Диагностика и отказоустойчивость

StructureChecker: severity-aware (error/warning), см. раздел «Диагностика».
OverviewController (System Health): древовидный дашборд доменов и алиасов.
Safe Mode: `www/admin/index.php` перехватывает фатальные ошибки и отдаёт понятную страницу.

---

## Безопасность

- Экранирование в `Attrs` и `Text` (XSS-защита).
- Пароли — только `password_hash`. Минимум 8 символов (`admin:passwd`, `user:new`).
- CSP из `engine.config` блокирует inline-скрипты.
- CSRF-токены обязательны на POST. Destructive — POST+CSRF (UI) или `--force` (CLI).
- Session: HttpOnly, SameSite=Lax, Secure при HTTPS.
- Path traversal guard: все CLI-команды с `--domain`/`--name` проверяют `..` и regex. `db:migrate --dir` ограничен `migrations/`.
- Карантин: destructive CLI-операции не удаляют сразу, а перемещают в `*_trash_<timestamp>/` (6 уровней: `app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`).
- SSRF-защита: UrlGuard в AINET, whitelist доменов, private IP block, ручные редиректы.
- Content: front controller `www/content.php` (GET/HEAD, `hostAllowed`, auth по `page_refs`, ETag, 304, SVG-CSP).
- Skin: `skin:edit` — whitelist файлов (`css/main.css`, `js/main.js`), 1 MB, блок `@import`/`javascript:`.

---

## Качество

- PHPStan level 8: 0 errors, 269 файлов (`src/`, `bin/`, `www/`).
- PHP-CS-Fixer: 0 issues, 269 файлов (`src/`, `bin/`, `www/`, `tests/`).
- PHPUnit: 570 тестов, 1671 assertion, 1 skipped.
- Тестовых файлов: 87.
- CI: phpunit + phpstan + cs-fixer `--dry-run`.

---

## Что не реализовано

Headless/REST API для внешних систем (кроме локального aicli), sitemap, hreflang, плагины, SMTP/сброс пароля, Redis/Memcached/CDN, мультиязычность контента, WYSIWYG, POST-запросы в AINET, парсинг HTML. См. `MANIFEST.md` §5.