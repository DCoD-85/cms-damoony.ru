Контракты проекта Damoony

Документ фиксирует текущие архитектурные контракты (интерфейсы и границы ответственности). Код реализаций скрыт за этими контрактами. Термины — по MANIFEST.md §2. Известные расхождения контракта и поведения — по MANIFEST.md §6–7.

**Принцип:** если интерфейс разрешает что-то, а runtime это ломает — это баг контракта, а не «особенность».

---

## DomainResolver (Разрешение доменов)

**Назначение:** определение реального домена и алиаса по входящему хосту.

**Методы:**

- `resolve(string $host): DomainContext`
- `exists(string $domain): bool`
- `real(string $domain): string`
- `namespace(string $domain): string`

**DomainContext (readonly):**

- `domain: string` — входной хост (как пришёл)
- `real: string` — канонический домен (папка в `app/`)
- `allias: string` — текущий алиас (ключ из `alliases_real`)
- `skin: string`
- `language: string`
- `available: bool` — доступен ли домен И алиас
- `stub: ?string`
- `namespace: string` — PHP-namespace
- `scheme: string` — имя схемы (используется в SchemeLoader)

**Инварианты:**

- Резолвер не бросает исключений → `DomainContext` с `available=false`.
- Отвечает только за вычисление контекста. Не загружает контроллеры и не рендерит страницы.
- Host нормализуется (порт, trailing-dot отрезаются).
- `real` — источник истины для путей в `app/`. Все CLI-команды, которые пишут в `app/`, строят путь через `realDomain`, а не через входной хост.

**Ограничения (P2):**

- `domains_real[$domain]` работает только при self-mapping (`"example.ru": "example.ru"`). Без self-mapping → `Undefined array key`.
- `DomainContext->allias` возвращает ключ из конфига, а папка на диске может называться иначе (если mapping не identity). Требует решения в P2.

**Источник:** `src/Domain/`

---

## Router (Маршрутизация)

**Назначение:** поиск класса контроллера по URL и контексту домена.

**Методы:**

- `match(string $path, DomainContext $domain): RouteMatch`

**RouteMatch (readonly):**

- `found: bool`
- `classChain: ?string` (например, `algoritm_about`)
- `pathRel: ?string`
- `args: array` (параметры из regex)
- `auth: bool`
- `code: int`

**Инварианты:**

- Роутер не выполняет код контроллера.
- Роутер не рендерит ошибки → `RouteMatch` с `found=false` и `code`.
- Правила — в `algoritm/algoritm.rules.json` (root) и `algoritm_{name}.rules.json` рядом со страницами.
- На каждом шаге роутинга FileRouter читает правила текущего класса (root → промежуточные → leaf).

**Ограничения:**

- Regex-параметры формата `regex|target`; `@preg_match` глушит ошибки — битый regex даст тихий `notFound`, не диагностику.

**Источник:** `src/Routing/`

---

## Region (Регионы и Блоки)

**Назначение:** единица шаблона (шапка, подвал, сайдбар), вставляемая в слоты схемы.

**Методы (RegionInterface):**

- `name(): string`
- `enabled(): bool`
- `render(RegionContext $context): Node`

**RegionRegistry:**

- `add(RegionInterface $region, bool $isBase = false)`
- `disable(string $name)` / `enable(string $name)`
- `useGlobal(string $name)`
- `useFiller(string $name)`
- `get(string $name): ?RegionInterface` — приоритет: `disabled` → `forcedGlobal` → `filler` → `base`

**Инварианты:**

- Регион не знает о других регионах и о схеме. Возвращает Node, не строку.
- Настройки (тип блока: `script`, `visual`, `mixed`) хранятся в `{name}.rules.json`.
- Источник истины — папки `.alliasglobal/*/`, а не глобальные манифесты.

**Ограничения:**

- `RegionContext::service(string): mixed` — Service Locator. `@template` в PHPDoc отсутствует (§6.2) — компенсация через `instanceof` на стороне вызывающего.
- RegionLoader молча пропускает битые блоки (класс не реализует `RegionInterface`) — диагностика через `structure:check`.

**Источник:** `src/Region/`

---

## UI (Дерево представления и Компоненты)

**Назначение:** типизированное дерево DOM, не строки.

**Базовые элементы:** `Node` (интерфейс), `Element` (HTML-тег), `Attrs` (атрибуты), `Builder` (хелперы, 12 методов), `Document` (html/head/body), `Meta` (title/CSS/JS), `Text`, `Fragment`, `Group`.

**Компоненты:** `Form`, `Field`, `Table`, `Button`, `Message`.

**Инварианты:**

- Дерево мутабельно: `append()` / `prepend()` / `content()` меняют узел in-place. `release()` возвращает HTML-строку.
- Экранирование данных — на уровне `Attrs` и `Text` (`htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)`).
- Имена атрибутов в `Attrs::release()` валидируются (XSS закрыт).
- `Document::release()` идемпотентен — повторный вызов не дублирует `<title>/<link>/<script>`.

**Ограничения:**

- `Node` — контракт с изъяном: `Text::append()`, `Slot::append()` бросают `LogicException`.
- `Slot::release()` возвращает `''` — слот без региона молча исчезает.
- `Builder` неполон (нет `table`, `form`, `section`, `nav`, `label`, `select`, `textarea`) — сложные структуры через `new Element()` или компоненты.

**Источник:** `src/Ui/`

---

## Scheme (Схемы)

**Назначение:** HTML-каркас страницы, собираемый из регионов через слоты.

**Методы:**

- `SchemeLoader::load(DomainContext $domain, string $schemeName = 'www'): array`
- `SchemeRenderer::render(array $manifest, RegionRegistry $regions, RegionContext $context): Element`

**Правило 3 файлов для схем:**

- `{name}.php` — Массив для рендеринга (`var_export`).
- `{name}.rules.json` — Настройки (`enabled`).
- `{name}.editor.json` — Дерево для UI (Drag&Drop).
- Глобальный манифест `schemes.json` удалён.

**Инварианты:**

- Схема — декларативный массив без бизнес-логики.
- Схема не знает о содержимом регионов.
- Иерархия: локальная схема алиаса → глобальная схема домена → 500. Скрытых дефолтов нет.

**Ограничения:**

- `SchemeCompiler::compile()` использует `var_export` (без `str_replace`).
- Визуальный редактор (Drag&Drop) сохраняет вложенность и обновляет `editor.json`, а затем компилирует в `php` через CLI `scheme:save`.

**Источник:** `src/Scheme/`

---

## Page (Страницы и Page-слой)

**Назначение:** единое представление страницы и её файлов (`php`, `rules.json`, `editor.json`).

**Базовый класс (`src/Algoritm/Page.php`):**

`Page implements PageInterface`

- `__construct(RegionContext, RegionRegistry, array $args)`
- `default(): Node|Response|null`
- `isAjax(): bool`
- `isPost(): bool`
- `isAuthenticated(): bool`
- `user(): mixed`
- `regions(): RegionRegistry`
- `setContent(string $slotName, Node $content): self`

**PageInterface (`src/Algoritm/PageInterface.php`):**

- `__construct(RegionContext, RegionRegistry, array $args)`
- `default(): Node|Response|null`
- `isAjax(): bool`
- `isPost(): bool`

**Page-слой (`src/Page/`):**

- `PagePath::resolve(Paths, domain, allias, name, parent): PagePath` — возвращает `targetRelPath`, `classChain`, `pageDir`, `phpFile`, `rulesFile`, `editorFile`, `parentRelPath`, `parentClassChain`, `parentDir`, `parentPhpFile`, `parentRulesFile`.
- `PageRules::load(string $file): array`
- `PageRules::save(string $file, array $rules): void`
- `PageRules::defaults(): array{available: list<string>, auth: bool, search: bool, default: bool}`
- `PageRules::withDefaults(array $current): array{...}`
- `PageRules::withChild(array $rules, string $childName): array`
- `PageNamespace::fromSite(array $site, string $domain): string`
- `PageCompiler::compile(namespace, classChain, blocks, withSchema = false): string` — компилирует JSON-блоки в PHP-код страницы. `withSchema=true` → оборачивает в схему. Используется в `page:write`, а также для stub-страниц в `domain:new`/`allias:new`/`structure:repair`.

**Инварианты:**

- Один класс = `algoritm_{name}.{php,rules.json,editor.json}` в одной директории.
- Root: `algoritm/algoritm.{php,rules.json,editor.json}`.
- Все 4 page-команды (`new/write/read/toggle`) используют `PagePath` и `PageRules` — без дублирования формул.
- `__root__` в CLI = пустой `name` внутри (главная страница). `--parent` с `__root__` — ошибка.
- `page:new` создаёт stub через PageCompiler (пустой `Builder::div('page-content')`).

**Ограничения:**

- Namespace `Damoony\Algoritm` сохранён (P2: переименовать в `Damoony\Page`).

**Источник:** `src/Algoritm/`, `src/Page/`

---

## Content (/content/)

**Назначение:** runtime-хранилище AI-контента, вне webroot. Отдаётся через `www/content.php`.

**Структура:**

```text
/content/
├── .trash/                         # карантин (runtime, .gitignore)
└── <domain>/<allias>/<category>/   # AI-контент (runtime, .gitignore)
    ├── <hash>.<ext>
    └── <hash>.meta.json
```

**Инварианты:**

- `<hash>` = sha256 содержимого (первые 16 символов).
- `<ext>` = `jpg|png|gif|webp|svg` (по MIME).
- `<hash>.meta.json`: `source_url`, `alt`, `size`, `mime`, `width`, `height`, `page_refs`, `hash`, `downloaded_at`.
- Дедупликация по hash: если файл есть — возврат существующего пути (warning «deduplicated»).
- Запись — **только** через `cli.php content:store`. AINET не пишет на диск.
- Удаление — только через `content:remove --force` (в карантин `/content/.trash/`).
- Отдача — через `www/content.php`: GET/HEAD, ETag, 304, `X-Content-Type-Options: nosniff`, SVG-CSP (`default-src 'none'; style-src 'unsafe-inline'`), auth по `page_refs`.
- Зоны не смешиваются: `www/media/admin/`, `www/media/shared/`, `www/media/<domain>/skin/` — git; `/content/<allias>/<category>/` — runtime, `.gitignore`; `/content/.trash/` — карантин.
- Валидация MIME: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`.
- Размер ≤ 10 MB; размеры ≥ 200×200 (для не-SVG).
- Domain — из `config/agent.config.allowed_domains`.
- Allias — `[a-z][a-z0-9_]*`, зеркалит `app/<domain>/<allias>/`.
- Category — `[a-z0-9_-]{1,32}`.

**Источник:** `src/Cli/Commands/ContentStoreCommand.php`, `ContentRemoveCommand.php`, `ContentListCommand.php`, `src/Content/ContentResponder.php`, `www/content.php`, `src/Files/Paths.php` (`contentRoot`, `content`).

### ContentResponder (front controller контракт)

**Класс:** `src/Content/ContentResponder.php`.

**Методы:**

- `parse(string $uri): ?ContentRequest` — `{domain, allias, category, hash, ext, originalUri}`.
- `hostAllowed(string $domain, string $host): bool` — true, если host маппится на domain.
- `requiresAuth(ContentRequest $req, array $meta, Paths $paths): bool` — по `page_refs`.
- `userCanAccess(bool $requiresAuth, ?AuthInterface $auth): bool`.
- `resolveMime(string $ext, array $meta): string`.
- `loginRedirectUrl(string $originalUri): string`.

**Инварианты:**

- Только GET/HEAD (POST/PUT/DELETE → 405 с `Allow: GET, HEAD`).
- 404 для несуществующих файлов, host-mismatch, битого `meta.json`.
- ETag = `"<hash>"`, `Cache-Control: public, max-age=31536000, immutable`, `Last-Modified` из `filemtime`.
- 304 Not Modified при `If-None-Match == ETag`.
- SVG: `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`.
- Auth: если `requiresAuth=true` и юзер не залогинен — 302 на `/login?return=<originalUri>`.

---

## Skin (www/media/<domain>/<allias>/skin/)

**Назначение:** визуальные темы алиаса (git-tracked инфраструктура).

**Структура:**

```text
www/media/<domain>/<allias>/skin/<name>/
├── skin.json          # {name, created_at, ...}
├── css/main.css
├── js/main.js
└── img/
```

**Инварианты:**

- Управление — только через CLI `skin:new/list/edit/remove`.
- `skin:edit` — только `css/main.css` и `js/main.js`, размер ≤ 1 MB, блок `@import` и `javascript:` URL (защита от inline-инъекций).
- `skin:remove` — в карантин `www/media/.trash/<domain>_<allias>_skin_<name>_trash_<ts>/`.
- Git-tracked (не `.gitignore`).
- Domain — из `config/agent.config.allowed_domains`.
- Allias, name — `[a-z][a-z0-9_]*`.

**Источник:** `src/Cli/Commands/SkinNewCommand.php`, `SkinListCommand.php`, `SkinEditCommand.php`, `SkinRemoveCommand.php`, `src/Files/Paths.php` (`mediaRoot`, `media`).

---

## Auth (Авторизация и Безопасность)

**Назначение:** аутентификация и защита от подделки запросов.

**Методы:**

- `AuthInterface`: `login()`, `logout()`, `isAuthenticated()`, `user()`
- `DbAuth` (база данных)
- `Csrf`: `field()`, `check()`
- `Page` (Базовый класс): `isAuthenticated(): bool`, `user(): mixed`

**Инварианты:**

- Пароли — только `password_hash`. Минимум 8 символов (`admin:passwd`, `user:new`).
- Все POST защищены CSRF. Без исключений.
- Флаг `auth: true` в `algoritm_{name}.rules.json` автоматически перенаправляет гостя на `/login`.
- Все destructive требуют POST+CSRF (UI) или `--force` (CLI). UI переведён на POST-формы.
- Админка использует отдельную авторизацию (`config/admin.config`), независимую от пользователей сайта.
- `admin:passwd` — валидация login (3-32 `[a-zA-Z0-9_.-]`) и password (≥8).
- `user:new` — валидация email (`FILTER_VALIDATE_EMAIL`), уникальность login и email, password ≥8.

**Источник:** `src/Auth/`, `src/Security/`, `src/Admin/Auth.php`

---

## I18n (Мультиязычность)

**Назначение:** перевод интерфейса админки и сайта.
**Классы:** `Translator` (`t(key, params)`), `LanguageNegotiator` (URL → Cookie → Конфиг).
**Хранилище:** файлы `lang/{code}.php`.
**Ограничения:** Фронтенд-переключатель и hreflang не реализованы. `Translator` создается вручную в FrontController (P2).

---

## Skins (Скины, UI-слой)

**Назначение:** управление визуальными темами.
**Классы:** `SkinManager`.
**Инварианты:** PageHandler добавляет `main.css`/`main.js` активного скина в Meta.
**Расхождение:** `PageHandler::applySkin()` читает `$_COOKIE['skin']` напрямую (P2).
**Ограничения:** UI-менеджера скинов нет (только выбор активного в настройках алиаса). CLI — `skin:new/list/edit/remove`.

---

## Low-Code и Компиляторы

**Назначение:** превращение JSON-структур в нативный PHP.

**Классы:**

- **PageCompiler:** Безопасная компиляция JSON-дерева контента (`h1`, `text`, `img`, etc) в PHP-код (класс Page). Использует только безопасные методы `Builder`. Применяется через CLI `page:write` (для AI), а также для stub-страниц (`domain:new`, `allias:new`, `structure:repair`). `withSchema=true` оборачивает в схему.
- **RegionCompiler:** JSON-дерево блока → PHP-класс. Требует флаг `security.allow_php_blocks=true` для генерации сырого PHP (`custom/if`).
- **SchemeCompiler:** массив схемы → `{name}.php` (`var_export`). Вызывается через CLI `scheme:save`.

**Инварианты:**

- Компиляторы вызываются только в момент сохранения (в админке через CLI или в CLI напрямую).
- На выходе — быстрый PHP с вызовами `Builder`, без парсинга JSON на лету.
- AI (через aicli) не может передать сырой PHP. Он передает только безопасный JSON, который компилируется PageCompiler.

---

## AINET (Сеть, SSRF-защита)

**Назначение:** безопасный выход AI в интернет для поиска и скачивания медиа.

**Библиотека `src/Ainet/` (НЕ фасад, AI вызывает через aicli):**

- **UrlGuard:** SSRF-защита.
  - `validate(string $url): array{url, host, port, scheme}`
  - Проверки: length ≤4096, scheme ∈ {http, https}, host не пустой, нет `user:pass`, port ∈ {80, 443, null}, прямой IP → private block, host ∈ DomainWhitelist, query ≤2048, hostname → DNS → private block, fragment отрезается.
- **DomainWhitelist:** точный матч (без wildcard).
  - `isAllowed(string $host): bool`
  - `fromConfig(array $config): self`
  - Пустой whitelist = всё запрещено (fail-safe).
- **HttpClient:** GET-only, ручные редиректы.
  - `get(string $url): HttpResponse`
  - `CURLOPT_FOLLOWLOCATION=false`, каждый Location → повторный UrlGuard.
  - `maxRedirects` (по умолчанию 3), `maxSize` (10 MB), `timeout` (connect 5s, read 20s).
- **Cache:** `sha256(url)` → file+meta, TTL 7 дней.
  - `get/put/clear/stats`.
  - `TTL=0` → кэш отключён.
- **CurlTransport implements HttpTransportInterface:** единственная реализация (для тестов — `FakeTransport`).
- **ImageSearch:**
  - `ProviderInterface`: `name()`, `requiresKey()`, `search(query, count): list<ImageResult>`
  - `OpenverseProvider` (fallback, без ключа): `ENDPOINT=api.openverse.org`
  - `WikimediaProvider` (default, без ключа): `ENDPOINT=commons.wikimedia.org`
- **AinetFacade:** публичный API для AICLI.
  - `status(): array`
  - `searchImages(query, count, providerName=null): array` — автофоллбэк primary → next (только default).
  - `fetchJson(url): array`
  - `fetchText(url): array`
  - `downloadImage(url, domain, allias, category, alt): array` — возвращает bytes + meta (БЕЗ записи на диск).

**Инварианты:**

- AINET не пишет на диск, не создаёт папки, не удаляет файлы.
- Единственная точка входа для AI — `bin/aicli.php`. `bin/ainet.php` — standalone, не в whitelist.
- `image:download`: AINET (bytes) → `cli.php content:store` (FS).
- URL whitelist в `config/ainet.config` (`allowed_domains`), независим от `agent.config` (domain whitelist).
- Rate limit: 30 req/min (`AinetConfig`). **Enforced** в `AinetFacade` через `RateLimiter` (файл `var/ainet-ratelimit.json`, fixed-window, `flock`). Кэш-хит лимит не тратит.

**Ограничения:**

- Только GET (POST/PUT/DELETE запрещены).
- Только image (PDF, ZIP не поддерживаются).
- Парсинг HTML не поддерживается (только JSON API и текст).
- Headless browser не используется.

**Источник:** `src/Ainet/`

---

## Diagnostics (Диагностика)

**Назначение:** проверка целостности системы.

**Классы:**

- **StructureChecker:** severity-aware проверка наличия всех 3 файлов для каждого элемента, стыковки филлер↔слот, отсутствия устаревших `.rules.json`.

**Методы:**

- `checkAllias(domain, allias): list<{level: string, message: string}>`
  - `level`: `'error' | 'warning'`

**Инварианты:**

- `error`: отсутствует `.php`, отсутствует `algoritm/algoritm.rules.json` (root), отсутствует схема.
- `warning`: отсутствует `.editor.json`, отсутствует `.rules.json` у блока/схемы, устаревший `{dir}/.rules.json` (→ `migrate:page-rules`), филлер без слота.
- Для вложенных страниц используется `classChain = algoritm_<rel_with_underscores>`, а не `basename(dir)`.
- Windows-разделители нормализуются в `/` перед вычислением `classChain`.

**CLI `structure:check`:**

- `$r->ok = true`, если нет errors (warnings допускаются).
- Вывод: `✗` для error, `⚠` для warning.
- Exit code: 0 при отсутствии errors, 1 при наличии errors.

**CLI `structure:repair`:**

- Восстанавливает отсутствующие root-страницы (`algoritm.php` через PageCompiler, `algoritm.rules.json`, `algoritm.editor.json`), `algoritm.rules.json` для схем, `{name}.rules.json/.editor.json` для блоков.
- Не трогает существующие страницы и настройки в `site.config`.
- Path traversal guard на domain.

---

## CLI, Админка и AI-Слой

**Назначение:** интерфейсы управления.

**CLI: 40 команд**, возвращают `Result`. Единственный источник истины для файловых операций.

**Полный список (40):** `help`, `domain:list`, `domain:new`, `domain:remove`, `allias:list`, `allias:new`, `allias:remove`, `block:list`, `block:new`, `block:edit`, `block:remove`, `filler:list`, `filler:new`, `filler:edit`, `filler:toggle`, `page:list`, `page:new`, `page:toggle`, `page:write`, `page:read`, `scheme:list`, `scheme:new`, `scheme:copy`, `scheme:remove`, `scheme:save`, `db:migrate`, `admin:passwd`, `user:new`, `trash:clear`, `structure:check`, `structure:repair`, `migrate:page-rules`, `content:store`, `content:remove`, `content:list`, `content:gc`, `skin:new`, `skin:list`, `skin:edit`, `skin:remove`.

**Инварианты CLI:**

- Destructive (`domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `content:remove`, `skin:remove`, `trash:clear`) — в карантин (`*_trash_<timestamp>/`), требуют `--force`.
- Path traversal guard: все команды с `--domain`/`--name` проверяют `str_contains('..')` и regex.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- `realDomain`: все команды, которые пишут в `app/`, строят путь по `realDomain` из `site.config`, а не по входному хосту.
- `trash:clear` — обход 6 уровней карантина (`app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`), symlink-safe `Fs::rmrf`.
- `db:migrate --dir` — ограничен подпапками `migrations/` (realpath check).
- `admin:passwd` — валидация login (3-32 `[a-zA-Z0-9_.-]`) + password (≥8).
- `user:new` — проверка уникальности login и email + password (≥8).
- `content:store` — MIME whitelist, размер ≤ 10 MB, размеры ≥ 200×200, дедупликация по hash.
- `skin:edit` — whitelist файлов (`css/main.css`, `js/main.js`), 1 MB, блок `@import`/`javascript:`.
- `*:list` — `Result::$data` (массив строк) + `--json`.

**Admin:** Тонкий клиент. Контроллеры делегируют работу CLI-командам (включая `scheme:copy/remove/save` и `content:*`). CodeMirror для страниц и блоков. Визуальный Drag&Drop только для Схем (через CLI `scheme:save`). POST защищены CSRF.

---

### AI-Слой (bin/aicli.php)

**Единственный фасад для LLM.** Принимает JSON-план, проверяет Roles/Navigator, конвертирует JSON-блоки в base64 и вызывает `bin/cli.php` (для `app/*` и `content:store`). Для `net:*` — вызывает `AinetFacade` (in-process). Не имеет доступа к `exec`/`system`.

**Роли (9):** `parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter`. Источник — `src/Ai/Roles.php`.

**Роль `*` (default):** union всех 9 ролей + специфичные (`ai:start`, `done`, `read_test_file`, `engine_status`, `filler_toggle`, `net:status`, `image:search`, `image:download`, `fetch`, `skin:remove`, `*:list`). Инвариант: `whitelist('*') ⊇ whitelist($role)` для всех ролей. Покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`.

**Navigator** (`src/Ai/Navigator.php`):

- `OFFERABLE = [domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, done]`.
- `start(array $state): list<{cmd, args, hint}>`.
- `after(string $cmd, array $args): list<{cmd, args, hint}>`.
- `next[]` фильтруется по whitelist роли (defense in depth).

**Запрещены всем ролям:** `user:new`, `db:migrate`, `admin:passwd`, `domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `trash:clear`, `migrate:page-rules`, `content:*` (запись только через `image:download`).

**Валидация аргументов:**

- `domain` — должен быть в `$allowedDomains` (из `config/agent.config`) для `app/*` и `image:download`.
- `url` для `net:*` — в `config/ainet.config` (`allowed_domains`).
- `allias`, `name`, `block`, `category` — regex.
- `page:write`: пустое `name` → `__root__` (главная страница); `--parent` с `__root__` — ошибка.
- `page:write`: domain проверяется по `$allowedDomains` (P0 закрыт).
- `image:download`: domain проверяется по `$allowedDomains` (P0 закрыт).
- `skin:edit`: `file` ∈ {`css/main.css`, `js/main.js`}.

**Инварианты:**

- Админка перехватывает фатальные ошибки (Safe Mode) и не падает при битом конфиге.
- Агент не может выполнить запрещенные команды (защита на уровне фасада).
- AI не может внедрить RCE (только безопасные JSON-блоки через PageCompiler).
- `page:write` в aicli проверяет domain по `$allowedDomains` (иначе — `RuntimeException`).
- `image:download` в aicli проверяет domain по `$allowedDomains` (иначе — `Security violation`).
- AINET URL whitelist проверяется UrlGuard (SSRF, private IP, scheme, port).
- Роль `*` — надмножество всех ролей (инвариант).
- Navigator `next[]` фильтруется по whitelist роли.

**Источник:** `bin/cli.php`, `bin/aicli.php`, `bin/ainet.php`, `www/admin/index.php`, `www/content.php`, `src/Ai/Roles.php`, `src/Ai/Navigator.php`, `src/Admin/`