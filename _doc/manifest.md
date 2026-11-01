MANIFEST — Канон намерений и фактического состояния

MANIFEST — это канонический перечень возможностей и ограничений Damoony Engine. Любой другой документ (readme.md, architecture.md, contracts.md, guide.md, admin.md, cli.md) производен от него.

**Правило расхождения:** MANIFEST описывает намерения. Код в src/ описывает поведение. Расхождение — это баг, который должен быть исправлен либо в коде (достичь намерения), либо в MANIFEST (скорректировать намерение). Не бывает «истины только в MANIFEST».

---

## 1. Идентичность

- PHP 8.4, self-hosted, multi-domain CMS.
- Multi-domain — first-class (в ядре, не плагин).
- **CLI-First** архитектура: CLI является единственным источником истины для файловых операций. Админка и AI-агенты — это лишь обертки над CLI.
- **AI-Native**: безопасный слой `bin/aicli.php` (единственная точка входа AI) + библиотека `src/Ainet/` (сеть, SSRF-защита). 9 ролей (`src/Ai/Roles.php`) + Navigator (`src/Ai/Navigator.php`). Рабочий оркестратор на Python (`agent_test.py`), успешно генерирующий сайты.
- **Low-Code**: визуальный редактор сохранен ТОЛЬКО для Схем (Drag&Drop). Блоки и Страницы пишутся через CodeMirror или AI (безопасный JSON).
- **Content**: `/content/<domain>/<allias>/<category>/` — runtime-хранилище AI-картинок (вне webroot, `.gitignore`). Отдаётся через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`). Запись только через CLI (`content:store`).
- **Skins**: `www/media/<domain>/<allias>/skin/<name>/{css,js,img}/` + `skin.json` — git-tracked инфраструктура. Управление через CLI (`skin:new/list/edit/remove`).
- Не WordPress, не Laravel CMS, не headless, не SaaS.

---

## 2. Терминология (без синонимов)

| Термин | Значение | Не путать с |
|---|---|---|
| Домен | Папка `app/<real_domain>/` | Входящий хост |
| Алиас | Подпапка `app/<real_domain>/<allias>/` | Поддомен |
| Схема | Каркас HTML в `.alliasscheme/<name>.php` | Страница |
| Слот | Позиция в схеме (top, center) | Регион |
| Регион | Класс `RegionInterface` в `.alliasglobal/<name>/` | Страница |
| Блок | Синоним региона | — |
| Филлер | Переопределение региона для алиаса | Глобальный регион |
| Страница | Класс `Page` (ранее `Algoritm`) в `algoritm/` | Регион |
| Тип блока | `script` / `visual` / `mixed` | — |
| Правило 3 файлов | Для всех сущностей: `{name}.php`, `{name}.rules.json`, `{name}.editor.json` | Конфиг |
| `__root__` | Специальное имя для записи в главную страницу аллиаса через CLI | — |
| realDomain | Канонический домен (папка в `app/`), резолвится из `site.config` | Входной хост |
| Content | Файл в `/content/<domain>/<allias>/<category>/<hash>.<ext>` + `<hash>.meta.json` | Skin |
| Skin | Скин алиаса в `www/media/<domain>/<allias>/skin/<name>/` | Content |
| Roles | 9 ролей AI-агента (`parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter`) | Navigator |
| Navigator | Граф `next[]` (HATEOAS) для AI и UI | Roles |
| AINET | Библиотека `src/Ainet/` (сеть, SSRF). Не фасад — AI вызывает через aicli. | `bin/ainet.php` (standalone) |

Запрещено: `"module"`, `"plugin"`, `"widget"`, `"template"`.

---

## 3. Реализовано (✅)

**Ядро:** DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

**Безопасность (P0 закрыт):** Удалены визуальные редакторы страниц/блоков (предотвращение RCE). В `RegionCompiler` добавлен флаг `security.allow_php_blocks` (по умолчанию false), блокирующий генерацию сырого PHP. Path traversal guard во всех CLI-командах (domain/allias/block/filler/scheme/content/skin): проверка `..` и regex. SSRF-защита в AINET (UrlGuard). Content front controller (`www/content.php`): GET/HEAD, `hostAllowed`, auth по `page_refs`, SVG-CSP. Skin: `skin:edit` — только `css/main.css` и `js/main.js`, 1 MB, блок `@import` и `javascript:` URL.

**Домен/Алиас:** `domain:new`, `domain:remove`, `allias:new`, `allias:remove`, `domains_*`, `alliases_*`, `stubs/*.html`. Все destructive-операции в карантин (`*_trash_<timestamp>/`), требуют `--force`. Idempotent: нет папки + нет в конфиге → warning, exit 0. Полная очистка конфига (16 ключей для домена, 8 для алиаса).

**Роутинг:** `algoritm/algoritm.rules.json` (root) и `algoritm_{name}.rules.json` (вложенные), флаги `available/auth/search/default`, regex-параметры, 404 для неизвестных путей.

**Схемы/Регионы:** SchemeLoader, SchemeRenderer, RegionLoader (сканирует `.alliasglobal/*/`), RegionRegistry, филлеры `<name>_<allias>.php`. CLI `scheme:new/copy/remove/save`. Копирование и удаление схем — через CLI (было: прямое в SchemeController).

**Правило 3 файлов:** Внедрено для Страниц, Блоков, Филлеров и Схем. Для страниц все три файла имеют единый префикс: `algoritm_{name}.{php,rules.json,editor.json}` в одной директории. Root — `algoritm/algoritm.{php,rules.json,editor.json}`. Глобальные `blocks.json` и `schemes.json` удалены.

**Page-слой:** PagePath, PageRules, PageNamespace — единый резолвинг путей, правил и namespace для всех page-команд (`new/write/read/toggle`). Устраняют дублирование формул.

**Безопасная компиляция (PageCompiler):** JSON-структура от AI конвертируется в нативный PHP через `Builder`. Поддерживаются блоки: `h1`, `h2`, `h3`, `text`, `img`, `link`, `button`, `ul`. Поддерживаются атрибуты: `class`, `id`, `style`, `alt`. Хардкод стилей удален.

**UI:** Node, Element, Attrs, Text, Fragment, Group, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message. XSS-экранирование на уровне Attrs/Text.

**Auth:** DbAuth (`password_hash`), SessionAuth, Csrf, login/logout, логирование в `var/admin_logs.json`, brute-force (5 → бан 15 мин). Базовые методы `Page::isAuthenticated()` и `user()`. `admin:passwd` — валидация login (3-32 `[a-zA-Z0-9_.-]`) + password ≥8. `user:new` — валидация email, уникальность login и email, password ≥8.

**БД:** PDO, MigrationRunner, MariaDB/MySQL. `db:migrate` — path traversal guard на `--dir` (только подпапки `migrations/`).

**Админка:** Вход/выход, профиль, I18n (RU/EN/ZH), Обзор системы, Домены, Алиасы, Блоки, Схемы, Филлеры, Страницы (дерево), Заглушки, Безопасность, Настройки. CodeMirror для всего. Визуальный Drag&Drop оставлен ТОЛЬКО для Схем. Safe Mode.

**AI-Слой:** `bin/aicli.php` — единственный безопасный фасад для управления движком через LLM. 9 ролей (`src/Ai/Roles.php`): `parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter`. Navigator (`src/Ai/Navigator.php`): `next[]` (HATEOAS), `OFFERABLE = [domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, done]`. Роль `*` (default) = union всех ролей + специфичные (`read_test_file`, `filler_toggle`, `net:*`, `skin:remove`, `*:list`). Работает по схеме `AI (Python/JSON) -> aicli.php -> {cli.php, AinetFacade}`. Поддерживает автономную диагностику и лечение, создание сайтов с картинками (успешно протестировано на Qwen 2.5 Coder 7B через `agent_test.py`). Два whitelist доменов: `config/agent.config` (`app/*` + `image:download.domain`) и `config/ainet.config` (URL для `net:*`). `*:list` (8 команд: `domain:list`, `allias:list`, `page:list`, `scheme:list`, `block:list`, `filler:list`, `content:list`, `skin:list`) с `--json` (`Result::$data`).

**AINET (библиотека, src/Ainet/):** SSRF-защита (UrlGuard: scheme, port, whitelist, private IP, ручные редиректы), HttpClient (GET-only, `CURLOPT_FOLLOWLOCATION=false`, повторный UrlGuard на каждый Location), Cache (`sha256(url)` → file+meta, TTL 7 дней), ImageSearch (OpenverseProvider + WikimediaProvider, автофоллбэк primary → next для default), AinetFacade (`searchImages`/`fetchJson`/`fetchText`/`downloadImage` — bytes в память, без записи), RateLimiter (`var/ainet-ratelimit.json`, fixed-window, `flock`, 30 req/min, enforced в AinetFacade; кэш-хит не тратит). `bin/ainet.php` — standalone-отладка, не в AI-whitelist.

**Content (/content/):** runtime-хранилище AI-контента (вне webroot). Отдаётся через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs` в `meta.json`). Три зоны `www/media/` (`admin/`, `shared/`, `<domain>/skin/`) — git; `/content/` — runtime, `.gitignore`; `/content/.trash/` — карантин. Файл: `<hash>.<ext>` (sha256, 16 символов) + `<hash>.meta.json` (`source_url`, `alt`, `mime`, `size`, `width`, `height`, `page_refs`, `hash`, `downloaded_at`). Дедупликация по hash. Запись — только через CLI `content:store`. AINET не пишет на диск: `image:download` → AinetFacade (bytes) → `shell_exec` → `cli.php content:store`.

**Skins (www/media/<domain>/<allias>/skin/):** git-tracked инфраструктура. CLI `skin:new/list/edit/remove`. `skin.json`, `css/main.css`, `js/main.js`, `img/`. `skin:edit` — только 2 файла, 1 MB, блок `@import`/`javascript:`. `skin:remove` — в карантин `www/media/.trash/`.

**Диагностика:** StructureChecker (наличие 3 файлов, стыковка филлер↔слот), CLI `structure:check` (severity-aware: warnings не валят команду), `structure:repair` (root через PageCompiler, editor.json, path traversal guard), `migrate:page-rules` (миграция `.rules.json` на новую модель).

**Миграция:** `migrate:page-rules` — одноразовая миграция старых `.rules.json` на новую модель. Идемпотентна. Шаги: (1) root `algoritm/.rules.json` → `algoritm/algoritm.rules.json`, (2) подпапки `{dir}/.rules.json` → `{dir}/algoritm_{name}.rules.json`, (3) нормализация полей, (4) создание пустых `.editor.json` для существующих `.php`.

**CLI (39 команд):** `help`, `domain:list`, `domain:new`, `domain:remove`, `allias:list`, `allias:new`, `allias:remove`, `block:list`, `block:new`, `block:edit`, `block:remove`, `filler:list`, `filler:new`, `filler:edit`, `filler:toggle`, `page:list`, `page:new`, `page:toggle`, `page:write`, `page:read`, `scheme:list`, `scheme:new`, `scheme:copy`, `scheme:remove`, `scheme:save`, `db:migrate`, `admin:passwd`, `user:new`, `trash:clear`, `structure:check`, `structure:repair`, `migrate:page-rules`, `content:store`, `content:remove`, `content:list`, `skin:new`, `skin:list`, `skin:edit`, `skin:remove`.

**trash:clear** — обход 6 уровней карантина (`app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`), флаги `--force`/`--older`/`--domain`, dry-run по умолчанию, symlink-safe `Fs::rmrf`.

**Fs:** `mkdir`, `exists`, `isDir`, `isFile`, `read`, `writeAtomic`, `rel`, `rmrf` (Windows-safe, symlink-safe). `rmrf` — единый источник истины для рекурсивного удаления.

**Качество:** PHPUnit (570 тестов, 1671 assertion — верифицировано; 1 skipped), PHPStan level 8 (0 errors, 269 файлов: `src/`, `bin/`, `www/`), PHP-CS-Fixer (0 issues, 269 файлов: `src/`, `bin/`, `www/`, `tests/`), GitHub Actions CI (phpunit + phpstan + cs-fixer --dry-run). Тестовых файлов — 87.

---

## 4. Частично реализовано (🟡)

| Фича | Что есть | Что нужно для ✅ |
|---|---|---|
| I18n фронтенда | Middleware, `DomainContext->language` | UI-переключатель на сайте, ключи `site.*`, hreflang |
| Skins | CLI `skin:new/list/edit/remove`, SkinManager, автоподключение CSS/JS | UI-менеджер в админке, переключатель на фронте |
| AI-Транзакции | AI умеет диагностировать, создавать страницы, скачивать картинки (`agent_test.py` E2E) | Откат (Rollback) при падении одной из команд в цепочке. |
| AI-Контекст | Успешная генерация 10 страниц с картинками | Разбиение длинных задач на батчи для слабых моделей (Qwen 7B). |

---

## 5. Не реализовано (❌)

WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API для внешних систем, Sitemap, `robots.txt`, hreflang, плагины, Schema.org, SMTP / сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента. POST в AINET (только GET). Парсинг HTML в AINET (только JSON API и текст). Скриншоты сайтов.

---

## 6. Осознанные архитектурные решения и известные дефекты

### 6.1. Страницы не идут через схему по умолчанию
**Статус:** ✅ Решено. `page:new` создает "голую" страницу. Разработчик добавляет `SchemeRenderer` вручную (Вариант A) или использует `page:write --with_schema=1` (для AI).

### 6.2. RegionContext::service() возвращает mixed
**Статус:** Осознанное решение. Компенсация — только `instanceof`-проверки. `@template` в PHPDoc — P2.

### 6.3. assert() для проверки типов из контейнера
**Статус:** Заменить `assert` на явные `if (!instanceof) throw` — P2.

### 6.4. domains_real[$domain] в save()
**Статус:** Не баг. P2 — заменить на `ConfigDomainResolver::real()`.

### 6.6. Builder неполон (нет table, form, section)
**Статус:** P2. Дополнить Builder методами.

### 6.17. Порт в Host не нормализуется
**Статус:** ✅ Закрыто (stage-3).

### 6.18. default(): null ломает страницу
**Статус:** ✅ Закрыто (stage-4, возвращает 204).

### 6.22. Принятый риск: пароль dev-БД в истории git
**Статус:** принятый риск. MariaDB слушает только localhost.

### 6.24. Защита от RCE в RegionCompiler
**Статус:** ✅ Закрыто. Визуальные редакторы удалены. `RegionCompiler` требует флаг `security.allow_php_blocks=true`.

### 6.25. Двойной слэш при записи главной страницы
**Статус:** ✅ Закрыто. PageWriteCommand использует `PagePath::resolve()`, который возвращает корректный путь без двойных слэшей.

### 6.26. Терминология Algoritm заменена на Page
**Проблема:** Класс `Algoritm` и интерфейс `AlgoritmInterface` конфликтовали с именами файлов `algoritm.php` из-за регистронезависимости PHP.
**Статус:** ✅ Решено (классы). Namespace `Damoony\Algoritm` пока сохранён (P2).

### 6.27. Старая модель .rules.json
**Проблема:** В старых версиях root rules были в `algoritm/.rules.json`, страницы в `{dir}/.rules.json`, `page:toggle` писал в 4-й файл. Это создавало дубли и мёртвые файлы.
**Статус:** ✅ Решено. Новая модель: `algoritm_{name}.rules.json` для каждой страницы, `algoritm/algoritm.rules.json` для root. Миграция — `migrate:page-rules`.

### 6.28. CLI-First для схем
**Проблема:** `scheme:copy/remove/saveVisual` были реализованы напрямую в SchemeController (файловые операции, не через CLI).
**Статус:** ✅ Решено. Новые команды `scheme:copy/remove/save`. SchemeController — тонкий клиент над CLI.

### 6.29. VarsExtractCommand (мёртвый код)
**Проблема:** Команда парсила `public $available` из PHP-класса (не существует в новой модели) и создавала `.data.json`, который движок не читает.
**Статус:** ✅ Удалён (команда + тест). Миграция старой модели — `migrate:page-rules`.

### 6.30. AINET + Content (переименовано из media)
**Проблема:** AI не мог получать картинки и данные из интернета. Сайты были только текстовые, `<img>` — внешние URL, которые могут отвалиться.
**Статус:** ✅ Решено. Библиотека `src/Ainet/` (SSRF-защита, HttpClient, Cache, ImageSearch, AinetFacade), CLI `content:store/remove/list`. `/content/<domain>/<allias>/<category>/` — runtime-хранилище (вне webroot). AINET не пишет на диск (bytes → CLI). Единственная точка входа для AI — `bin/aicli.php` (`net:*` in-process). `bin/ainet.php` — standalone-отладка.

### 6.31. Openverse 301 → .org
**Проблема:** Openverse переехал с `api.openverse.engineering` на `api.openverse.org` (301), плюс timeout из РФ.
**Статус:** ✅ Решено. ENDPOINT обновлён, `default_provider = wikimedia`, `read_timeout = 20s`, auto-fallback primary → next (только default).

### 6.32. Media → Content: переезд вне webroot
**Проблема:** AI-контент лежал в `www/media/<domain>/<allias>/<category>/` (внутри webroot), что давало прямой доступ к файлам без auth-check.
**Статус:** ✅ Решено. `/content/` (вне webroot) + `www/content.php` front controller (GET/HEAD, `hostAllowed`, auth по `page_refs`, ETag, 304, SVG-CSP). CLI `media:*` → `content:*`. AINET не пишет на диск (bytes → `content:store`). Runtime-артефакты (`/content/`, `app/damoony3test.ru/`, `config/ainet.config`, `config/agent.config`) убраны из git (`.gitignore`).

### 6.33. AI-слой: Roles + Navigator + role='*'
**Проблема:** whitelist `aicli.php` был плоским списком (12 команд), роль `*` не была надмножеством ролей (были утеряны `block:new`, `scheme:new`, `filler:new`, `scheme:save`, `structure:check`, `structure:repair`).
**Статус:** ✅ Решено. 9 ролей (`src/Ai/Roles.php`), Navigator (`src/Ai/Navigator.php`), роль `*` = union всех ролей + специфичные. Инвариант покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`.

---

## 7. Известные баги (требуют исправления)

### 7.1. P0 — Блокируют production (6 из 6 закрыто)

- ~~CSRF на destructive GET.~~ ✅ Закрыто.
- ~~addslashes в компиляторах.~~ ✅ Закрыто.
- ~~`Response::internalError($e->getMessage())`.~~ ✅ Закрыто.
- ~~Два формата `.edit.json`.~~ ✅ Закрыто.
- ~~`SchemeCompiler::str_replace`.~~ ✅ Закрыто.
- ~~Нормализация Host.~~ ✅ Закрыто.
- ~~RCE через custom/if блоки.~~ ✅ Закрыто.

### 7.2. P1 — Надёжность и безопасность (7 из 7 закрыто)

- ~~Document::release() не идемпотентен.~~ ✅ Закрыто.
- ~~XSS в именах атрибутов.~~ ✅ Закрыто.
- ~~SchemeLoader fallback.~~ ✅ Закрыто.
- ~~Схлопывание вложенности схем.~~ ✅ Закрыто.
- ~~PageController::edit() не резолвит хост.~~ ✅ Закрыто.
- ~~`default(): null`.~~ ✅ Закрыто.
- ~~StructureChecker не проверяет схемы.~~ ✅ Закрыто.

### 7.3. P2 — Техдолг

- Реализовать Rollback (транзакции) для AI-агента.
- Разбить длинные AI-задания на батчи (для Qwen 7B).
- `domains_real[$domain]` → `ConfigDomainResolver::real()`.
- Builder — дополнить (`table`, `form`, `section`, `nav`, `label`, `select`, `textarea`).
- `assert()` → явные проверки.
- `RegionContext::service()` — добавить `@template` в PHPDoc.
- Translator/LanguageNegotiator через контейнер.
- `PageHandler::applySkin` — не читать `$_COOKIE` напрямую.
- Переименовать namespace `Damoony\Algoritm` → `Damoony\Page`.
- AINET: POST/PUT/DELETE — не реализованы (только GET).
- AINET: парсинг HTML — не реализован (только JSON API и текст).
- Content: orphan cleanup (неиспользуемые hash) — не реализован.
- `ai_agent/` (Planner→Builder→Content) — прототип, не используется. Рабочий runner — `agent_test.py`.
- `skin:bundle` — не реализован.

---

## 8. Инварианты

- Домен ≠ Алиас.
- DomainResolver не бросает исключений.
- FileRouter не рендерит ошибки.
- Все POST защищены CSRF.
- Пароли — `password_hash`. Минимум 8 символов.
- Ядро знает только `RegionInterface`.
- Kernel ловит `Throwable`, белых экранов нет.
- Destructive операции — только POST+CSRF (UI) или `--force` (CLI).
- Host нормализуется.
- `default(): null` не ломает runtime.
- Имена атрибутов валидируются.
- Скрытых дефолтов нет.
- `Document::release()` идемпотентен.
- Правило 3 файлов: для страниц все три файла имеют единый префикс `algoritm_{name}.{php,rules.json,editor.json}` в одной директории. Root — `algoritm/algoritm.{...}`.
- AI не пишет сырой PHP, а использует PageCompiler (JSON).
- CLI-First: все файловые операции (включая `scheme:*`, `content:*`, `skin:*`) вызываются через CLI. Админка — тонкий клиент.
- Path traversal guard: все CLI-команды с `--domain`/`--name` проверяют `..` и regex.
- Карантин: все destructive CLI-операции используют `*_trash_<timestamp>/`, требуют `--force`. 6 уровней.
- realDomain: пути в `app/` строятся по `realDomain` из `site.config`, а не по входному хосту.
- Content: запись только через CLI `content:store`. AINET не пишет на диск. Front controller `www/content.php`. Auth по `page_refs`.
- Skin: запись только через CLI `skin:edit` (`css/main.css`, `js/main.js`, 1 MB, блок `@import`/`javascript:`).
- AINET: URL whitelist (`config/ainet.config`) независим от domain whitelist (`config/agent.config`). UrlGuard проверяет scheme/port/private IP/whitelist на каждом редиректе.
- AI-Слой: единственная точка входа — `bin/aicli.php`. `bin/ainet.php` не в AI-whitelist.
- Роли: роль `*` — надмножество всех ролей (инвариант, покрыт тестом).
- Navigator: `next[]` фильтруется по whitelist роли.
- Два whitelist доменов: `agent.config` (`app/*`, `image:download.domain`) + `ainet.config` (URL `net:*`).
- `Fs::rmrf` — единый рекурсивный метод (Windows-safe, symlink-safe), используется в `trash:clear` и Cache.

---

## 9. Источники истины (порядок приоритета)

1. Код в `src/` — фактическое поведение.
2. `MANIFEST.md` — намерения.
3. `_doc/` — производная документация.

---

## 10. Bug Backlog (по приоритетам)

**Закрыто (текущий, 2026-11-02):**

- `block:edit` / `filler:edit` зарегистрированы в `bin/cli.php` (39 команд).
- Удалён мёртвый `www/media/admin/page-builder.css` + класс `page-builder-app` из `PageController`.
- PHPStan/CS-Fixer: 0 errors / 0 issues (269 файлов, 570 тестов).
- Остальное (page-rules unify, CLI-аудит, AINET, Content, Skin, Roles/Navigator, `agent_test.py`, git-гигиена) — в §3.

---

## 11. Позиция архитектора (финальная)

- Текущее состояние: 99% production-ready.
- 0 P0-блокеров. 0 P1-блокеров.
- Оценка 9.8/10 (после AINET + content + skin + Roles/Navigator).
- Как AI-платформа: 9.9/10 (AI успешно строит сайты с картинками за 1 шаг через `agent_test.py`).

---

## 12. Видение (6–12 месяцев)

**Low-Code:** Схемы (Drag&Drop) для контент-менеджеров. Страницы и Блоки (CodeMirror) для разработчиков.

**AI-платформа:** Транзакции и Rollback. Батчи для слабых моделей. AINET POST/HTML-парсинг. Изоляция тестовых доменов. Облачный SaaS-конструктор на базе Damoony. Multi-agent оркестрация (9 ролей) через `agent_architecture.md`.

**Multi-domain:** 10+ доменов на одном инстансе. Кеш `scandir` для RegionLoader. Content CDN для больших сайтов.