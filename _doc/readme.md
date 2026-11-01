Damoony Engine

Лёгкий PHP 8.4 движок для мультидоменных сайтов с архитектурой CLI-First и нативной интеграцией AI-агентов.

**Статус:** 99% production-ready. 0 P0-блокеров. Полное состояние — MANIFEST.md.

---

## Что это

Damoony Engine — это AI-Native CMS. Она сочетает в себе скорость статического сайта (рендеринг за 0.8–3 мс) и мощь современных AI-агентов.

Главное отличие от классических CMS:

1. **CLI-First**: CLI — это единственный источник истины. Админка и AI-агенты — это лишь безопасные обертки над CLI-командами.
2. **Безопасный Low-Code**: Визуальный Drag&Drop (конструктор) оставлен ТОЛЬКО для Схем (HTML-каркасов). Страницы и Блоки редактируются через CodeMirror или AI (через безопасный JSON). Это исключает RCE-уязвимости.
3. **AI-Интеграция**: Встроенный слой `bin/aicli.php` (единственная точка входа для AI) + библиотека `src/Ainet/` (безопасный выход в интернет: поиск картинок, fetch данных) + 9 ролей (`src/Ai/Roles.php`) + Navigator (`src/Ai/Navigator.php`) позволяют локальным LLM (например, Ollama) безопасно диагностировать систему, создавать сайты с картинками и наполнять страницы реальным контентом. Рабочий E2E-оркестратор — `agent_test.py`.

---

## Стек

- PHP >= 8.4
- Composer (PSR-4, PSR-3, PSR-11)
- PHPUnit 10 (570 тестов, 1671 assertion — верифицировано; 1 skipped — env-dependent)
- PHPStan (level 8, 269 файлов: src/, bin/, www/)
- PHP-CS-Fixer (269 файлов: src/, bin/, www/, tests/)
- MariaDB / MySQL (production) через PDO
- SQLite (тесты)
- Nginx + PHP-FPM (или Apache + mod_php)
- Ollama (опционально, для AI-агента: Qwen 2.5 Coder 7B / 14B)

---

## Структура

```text
bin/cli.php        — точка входа CLI (Ядро)
bin/aicli.php      — единственный безопасный фасад для AI-агентов
bin/ainet.php      — standalone-отладка AINET (не для AI)
agent_test.py      — рабочий E2E-оркестратор (Ollama ↔ aicli)
ai_agent/          — прототип (Planner→Builder→Content), не используется (P3)
config/            — JSON-конфиги (site.config, engine.config, admin.config, agent.config, ainet.config)
src/               — исходный код (PSR-4, Damoony)
src/Ai/            — Roles (9 ролей) + Navigator (next[] HATEOAS)
src/Ainet/         — библиотека безопасного выхода в интернет (SSRF, HTTP, ImageSearch)
src/Content/       — ContentResponder (front controller для /content/)
lang/              — файлы переводов (ru.php, en.php, zh.php)
stubs/             — HTML-шаблоны для 503
www/               — публичная директория (index.php, admin/index.php, content.php, media/)
www/content.php    — front controller /content/ (GET/HEAD, ETag, 304, SVG-CSP, auth)
www/media/         — git-tracked инфраструктура (admin/, shared/, <domain>/<allias>/skin/)
                     + runtime-карантин .trash/ (в .gitignore)
                     + runtime-карантин .trash/ (в .gitignore)
content/           — runtime AI-контент (вне webroot, .gitignore)
app/<domain>/      — пользовательские домены
migrations/        — SQL-миграции
var/               — runtime (логи, бан-листы, ratelimit, ainet-cache)
_dev/              — тесты, конфиги PHPUnit/PHPStan
_doc/              — документация
```

---

## Ключевые особенности

**Мультидоменность:** управление многими сайтами из одной админки. Домен (инфраструктура) + Алиас (контент).

**Правило 3 файлов:** Любая сущность (Страница, Блок, Схема, Скин) состоит из трёх файлов: `.php` (код), `.rules.json` (настройки), `.editor.json` (данные UI). Для страниц все три с единым префиксом: `algoritm_{name}.{php,rules.json,editor.json}`. Root — `algoritm/algoritm.{php,rules.json,editor.json}`. Никаких глобальных манифестов.

**AI-Агенты:** Агент (успешно протестирован на Qwen 2.5 Coder 7B через Ollama) может автономно создавать 10-страничные сайты с меню и стилями за один шаг через `aicli.php` (рабочий runner — `agent_test.py`). Умеет искать и скачивать картинки (`image:search` → `image:download`), получать данные из Wikipedia/GitHub API (`fetch`). 9 ролей + Navigator (`next[]` HATEOAS): роль `*` (default) = union всех ролей + специфичные.

**AINET (безопасный интернет для AI):** Библиотека `src/Ainet/` с SSRF-защитой (whitelist доменов, блок private IP, только GET, ручные редиректы, rate limit 30/min, кэш 7 дней). Провайдеры картинок: Wikimedia (default, без ключа), Openverse (fallback, без ключа). AINET не пишет на диск — байты передаются в CLI (`content:store`).

**Content (/content/):** runtime-хранилище AI-контента, вне webroot. Отдаётся через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`). Файл: `<hash>.<ext>` (sha256, 16 символов) + `<hash>.meta.json` (source_url, alt, mime, size, width, height, page_refs, hash, downloaded_at). Дедупликация по hash. Запись — только через `cli.php content:store` (единственный источник истины для FS).

**Skin (www/media/<domain>/<allias>/skin/):** git-tracked инфраструктура. CLI `skin:new/list/edit/remove`. `skin:edit` — только `css/main.css` и `js/main.js`, 1 MB, блок `@import`/`javascript:`.

**Безопасная компиляция:** PageCompiler конвертирует JSON-структуру от AI в нативный PHP через Builder (защита от RCE).

**Строгая типизация блоков:** script, visual, mixed. Настройки хранятся в `.rules.json`.

**UI как дерево:** типизированное дерево Node/Element/Attrs с XSS-экранированием.

**Диагностика:** StructureChecker (проверка наличия всех 3 файлов, severity-aware), CLI `structure:check` и `structure:repair`.

**Миграция:** `migrate:page-rules` — одноразовый перевод старых `.rules.json` на новую модель (`algoritm_{name}.rules.json`). Идемпотентна.

**Безопасность CLI:** все destructive-операции (`domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `content:remove`, `skin:remove`, `trash:clear`) в карантин (`*_trash_<timestamp>/`), требуют `--force`. Path traversal guard во всех командах с `--domain`/`--name`. Idempotent remove: нет папки + нет в конфиге → warning, exit 0. **6 уровней карантина** (`app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`).

---

## Принципы

- **Домен** — единица организации. Всё скоупится по домену и алиасу.
- **UI** — это дерево. Типизированное дерево узлов, не строки.
- **CLI-First.** Любая файловая операция вызывается через CLI. Админка и AI делегируют работу ему.
- **Explicit over implicit.** Нет `$GLOBALS`, статических кэшей, магии `__get`.
- **Безопасность** — основа. Удалены визуальные редакторы для блоков/страниц. RCE закрыт. SSRF-защита. Path traversal guard.

---

## Быстрый старт

Убедитесь, что в `config/engine.config` прописаны доступы к MariaDB, затем:

```bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=<strong>
php bin/cli.php user:new --login=admin --email=admin@example.com --password=<strong>
```

Откройте `http://<домен>/` (сайт) и `http://<домен>/admin/` (админка). Страницы с `"auth": true` в `.rules.json` требуют входа через `/login`.

⚠️ `user:new` создаёт пользователя сайта (для страниц с auth), не администратора. Админка использует отдельную авторизацию (`admin.config`). Пароль — минимум 8 символов (для `admin:passwd` и `user:new`).

⚠️ Если на диске остались старые `.rules.json` (до рефакторинга) — запустите `php bin/cli.php migrate:page-rules`. Команда идемпотентна и безопасна.

⚠️ Для AI-агента (Ollama) создайте `config/agent.config` (`allowed_domains`) и `config/ainet.config` (из `config/ainet.config.example`). Без `agent.config` AI не сможет создать домен. Без `ainet.config` `net:*` команды вернут fail-safe ошибку.

Подробнее: `install.md`, `cli.md`, `architecture.md`, `guide.md`, `admin.md`, `ai_agent.md`, `ainet.md`.

---

## Статус (по MANIFEST.md §3–4)

### Реализовано (✅)

**Ядро:** DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

**Безопасность (P0 закрыт):** Удалены визуальные редакторы страниц/блоков. В `RegionCompiler` добавлен флаг `security.allow_php_blocks` (по умолчанию false). Path traversal guard в CLI-командах. SSRF-защита в AINET (UrlGuard). Content front controller (`www/content.php`): GET/HEAD, `hostAllowed`, auth по `page_refs`, SVG-CSP. Skin: `skin:edit` — whitelist файлов, 1 MB, блок `@import`/`javascript:`.

**Домен/Алиас:** `domain:new`, `domain:remove`, `allias:new`, `allias:remove`, `domains_*`, `alliases_*`. Destructive — в карантин + `--force`. Полная очистка конфига (16 ключей для домена, 8 для алиаса).

**Роутинг:** `algoritm/algoritm.rules.json` (root) и `algoritm_{name}.rules.json` (вложенные), флаги `available/auth/search/default`, regex-параметры, 404.

**Схемы/Регионы:** SchemeLoader, SchemeRenderer, RegionLoader, RegionRegistry, филлеры. CLI `scheme:new/copy/remove/save`.

**Правило 3 файлов:** Внедрено для Страниц, Блоков, Филлеров и Схем. Для страниц — единый префикс `algoritm_{name}.{php,rules.json,editor.json}`, root — `algoritm/algoritm.{...}`.

**Page-слой:** PagePath, PageRules, PageNamespace — единый резолвинг путей/правил/namespace для всех page-команд.

**Безопасная компиляция:** PageCompiler (JSON → PHP) для страниц.

**UI:** Node, Element, Attrs, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message.

**Auth:** DbAuth, SessionAuth, Csrf, login/logout, логирование, брутфорс-защита. Базовый класс Page. `admin:passwd` — валидация login/password, `user:new` — проверка email и уникальности.

**БД:** PDO, MigrationRunner, MariaDB/MySQL. `db:migrate` — path traversal guard на `--dir`.

**Админка:** Тонкий клиент. CodeMirror для страниц/блоков. Визуальный Drag&Drop для схем (через CLI `scheme:save`). I18n (RU/EN/ZH), System Health, Safe Mode. `SchemeController` — тонкий клиент над CLI (`scheme:*`).

**AI-Слой:** `bin/aicli.php` — единственный безопасный фасад для интеграции с Ollama. 9 ролей (`src/Ai/Roles.php`): `parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter`. Navigator (`src/Ai/Navigator.php`): `next[]` HATEOAS. Роль `*` (default) = union всех ролей + специфичные. Поддерживает `app/*`, `content:store`, `net:*`, `*:list` (8 команд с `--json`). Два whitelist доменов (`agent.config` + `ainet.config`). Рабочий runner — `agent_test.py` (Ollama ↔ aicli).

**AINET:** Библиотека `src/Ainet/` — UrlGuard (SSRF), HttpClient (GET-only, ручные редиректы), Cache (sha256 → file+meta, TTL 7 дней), ImageSearch (wikimedia default, openverse fallback, автофоллбэк), AinetFacade (bytes в память, без записи). `bin/ainet.php` — standalone-отладка.

**Content:** `/content/<domain>/<allias>/<category>/<hash>.<ext>` + `meta.json` (вне webroot). Дедупликация по hash. Запись только через CLI `content:store`. `www/content.php` — front controller (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`). `/content/.trash/` — карантин.

**Skin:** `www/media/<domain>/<allias>/skin/<name>/`. CLI `skin:new/list/edit/remove`. `skin:edit` — только 2 файла, 1 MB.

**CLI (39 команд):** включая `domain:list`, `allias:list`, `page:list`, `scheme:list`, `block:list`, `filler:list`, `domain:remove`, `page:write`, `page:read`, `block:edit`, `filler:edit`, `scheme:copy/remove/save`, `migrate:page-rules`, `structure:check`, `structure:repair`, `trash:clear` (6 уровней), `content:store/remove/list`, `skin:new/list/edit/remove`.

**Fs:** mkdir, exists, isDir, isFile, read, writeAtomic, rel, rmrf (Windows-safe, symlink-safe). `rmrf` — единый источник истины для рекурсивного удаления.

**Качество:** PHPUnit (570 тестов, 1671 assertion), PHPStan level 8 (0 errors, 269 файлов), PHP-CS-Fixer (0 issues, 269 файлов), CI. Тестовых файлов — 87.

### Частично (🟡)

- **I18n фронтенда:** middleware и `DomainContext->language` есть, UI-переключателя и hreflang нет.
- **Skins:** CLI `skin:new/list/edit/remove`, SkinManager и автоподключение CSS/JS есть, UI-менеджера и переключателя нет.
- **AI-Транзакции:** AI умеет диагностировать, лечить, создавать страницы с картинками (`agent_test.py` E2E), но нет отката (Rollback) при падении одной из команд в цепочке.
- **AI-Батчи:** длинные задачи (>5 шагов) требуют разбиения для слабых моделей (Qwen 7B) — ручное.

### Не реализовано (❌)

WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API для внешних систем (кроме aicli), sitemap, robots.txt, hreflang, плагины, Schema.org, SMTP/сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента. AINET POST/PUT/DELETE (только GET). Парсинг HTML в AINET (только JSON API и текст). Скриншоты сайтов.

---

## Известные проблемы (по MANIFEST.md §7)

### P0 — блокируют production

Нет. Все 6 P0 закрыты.

### P1 — надёжность и безопасность

Нет. Все 7 P1 закрыты.

### P2 — техдолг


---

## Производные документы

- `MANIFEST.md` — канон. Источник истины о намерениях.
- `install.md` — развёртывание.
- `cli.md` — CLI-команды (39 команд).
- `admin.md` — админка.
- `guide.md` — руководство разработчика (Вариант A/B, блоки, схемы, картинки, AINET).
- `architecture.md` — жизненный цикл запроса (включая AI-слой, AINET, content, skin).
- `contracts.md` — контракты (интерфейсы и ограничения).
- `philosophy.md` — 10 принципов.
- `scenarios.md` — ключевые сценарии.
- `ai_agent.md` — инструкции и спецификация для AI-агентов (через aicli.php, включая сценарий «сайт с картинками»).
- `agent_architecture.md` — Navigator + 9 ролей, Multi-Agent оркестрация (P2/P3).
- `ainet.md` — дизайн библиотеки AINET (SSRF, HttpClient, Cache, ImageSearch, Facade).