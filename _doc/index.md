Документация Damoony Engine

Вся документация по проекту. Разделена по назначению: разработчики, администраторы, AI-агенты.

**Статус проекта:** 99% production-ready. 0 P0-блокеров. Полное состояние — MANIFEST.md.

⚠️ Порядок приоритета при расхождении: код в `src/` → `MANIFEST.md` → остальные документы `_doc/` → комментарии в коде. Если документ противоречит `MANIFEST.md` — доверяйте `MANIFEST.md`.

---

## Канон

**`MANIFEST.md`** — главный документ. Возможности (✅/🟡/❌), известные дефекты (§6), баги P0/P1/P2 (§7), инварианты (§8), backlog (§10), видение (§12). **Читать первым.**

---

## Общие сведения

**`readme.md`** — обзор проекта, стек, быстрый старт, честный статус, известные проблемы.

**`philosophy.md`** — 10 принципов архитектуры и степень их соблюдения.

---

## Архитектура и контракты

**`architecture.md`** — жизненный цикл HTTP-запроса, слои, Low-Code-компиляция, AI-слой (Roles + Navigator), AINET, content (`/content/` + `www/content.php`), skin, Safe Mode, realDomain, карантин (6 уровней).

**`contracts.md`** — контракты (DomainResolver, Router, Region, UI, Scheme, Page, Content, ContentResponder, Skin, Auth, I18n, Skins, компиляторы, AINET, диагностика, CLI/AI-слой с Roles/Navigator).

**`scenarios.md`** — ключевые Use Cases со статусом ✅/🟡/🔴 и покрытием тестами (включая миграцию, CLI-безопасность, content:*, skin:*, AINET, Roles, Navigator, AI-слой).

---

## Установка и использование

**`install.md`** — развёртывание (MariaDB, конфиги, веб-сервер, allow_php_blocks, ainet.config, agent.config, `/content/` вне webroot, skin-зоны).

**`cli.md`** — 37 CLI-команд (включая `*:list` × 6, `scheme:*`, `content:store/remove/list`, `skin:new/list/edit/remove`, `migrate:page-rules`, `structure:check`, `structure:repair`, `trash:clear` — 6 уровней), флаги, идемпотентность, path traversal guard.

**`admin.md`** — админка: вход, домены, алиасы, блоки, схемы, дерево страниц, content, skin. CodeMirror и визуальный Drag&Drop (только для Схем, через CLI `scheme:save`). Ограничения (что только через CLI).

**`guide.md`** — руководство разработчика: Вариант A (со схемой) и Вариант B (без), правило 3 файлов (для Страниц, Блоков, Филлеров, Схем, Скинов), безопасное наполнение контента через JSON (`page:write`), картинки через `image:download → content:store`, миграция `migrate:page-rules`, path traversal и карантин CLI (6 уровней), чек-лист перед деплоем.

---

## Инструкции для AI-агентов

**`ai_agent.md`** — Единая инструкция для AI. Включает: архитектуру (System), безопасную генерацию JSON-контента (Logic), сценарий «сайт с картинками» (AINET), спецификацию безопасного фасада (`bin/aicli.php`), роли (9), Navigator (`next[]` HATEOAS), whitelist `*` = union всех ролей. AI не пишет сырой PHP.

**`agent_architecture.md`** — архитектура AI-агента: Navigator + 9 ролей, Multi-Agent оркестрация, tasks.json/context.json, `agent:new/step/status/run` (P2), `agent_test.py` как рабочий runner, `ai_agent/` как прототип (P3).

---

## Дизайн AINET

**`ainet.md`** — дизайн библиотеки AINET: зачем, архитектура (AI → AICLI → {CLI, AINET(lib)} → CLI(FS)), команды (`net:status`, `image:search`, `image:download`, `fetch`), SSRF-защита (UrlGuard, DomainWhitelist, private IP, ручные редиректы), Cache, провайдеры (wikimedia default, openverse fallback), `config/ainet.config`, `www/content.php`, формат ответа, что НЕ делает AINET.

---

## Планы и Roadmap

**`roadmap.md`** — глобальные планы развития (AI, SEO, E-commerce, Enterprise). ⚠️ Планы, не реальность.

---

## Быстрый маршрут по ролям

| Роль | Читать |
|---|---|
| Новый разработчик | `readme.md` → `MANIFEST.md` → `architecture.md` → `contracts.md` → `guide.md` |
| Администратор | `readme.md` → `admin.md` → `cli.md` |
| AI-агент (Универсальный) | `MANIFEST.md` → `ai_agent.md` → `agent_architecture.md` → `cli.md` → `ainet.md` |
| Интегратор AI (Python/LLM) | `ai_agent.md` → `agent_architecture.md` → `ainet.md` → `cli.md` → `architecture.md` |
| Перед деплоем | `MANIFEST.md` §7, §10 → `guide.md` §14 → `admin.md` §«Что НЕ работает» |
| Перед визуальным сохранением (Схемы) | `admin.md` §«Визуальный редактор» + `MANIFEST.md` §7.1 п.4 (P0) |
| Восстановление структуры | `cli.md` (раздел `structure:repair`) |
| Миграция `.rules.json` | `cli.md` (раздел `migrate:page-rules`) + `guide.md` §13 |
| Очистка карантина | `cli.md` (раздел `trash:clear`, **6 уровней**) |
| Path traversal / безопасность CLI | `guide.md` §15 + `cli.md` (каждая команда) |
| AINET / картинки для AI | `ainet.md` + `ai_agent.md` §4 (сценарий «сайт с картинками») |
| Content (`/content/`) | `architecture.md` §«Слой контента и скинов» + `contracts.md` §«Content» + `cli.md` (`content:*`) |
| Skin (`www/media/.../skin/`) | `architecture.md` §«Слой контента и скинов» + `contracts.md` §«Skin» + `cli.md` (`skin:*`) |
| Roles / Navigator (AI-слой) | `agent_architecture.md` + `ai_agent.md` §2 + `contracts.md` §«AI-Слой» |
| Планирование будущего | `roadmap.md` |

---

## Что читать НЕ нужно (устарело)

Ранние версии документов упоминали «235 тестов», «61 тестовый файл», «295 тестов», «472 теста», «27 CLI-команд», «25 CLI-команд», «22 CLI-команды», «24 CLI-команды», «vars:extract», «100% паритет CLI/UI», «WYSIWYG», «blocks.json», «schemes.json», «визуальные редакторы страниц и блоков», «класс Algoritm» (переименован в `Page`), «AI пишет PHP-код» (теперь только JSON), «root rules в `algoritm/.rules.json`» (теперь `algoritm/algoritm.rules.json`), «`{dir}/.rules.json`» (теперь `algoritm_{name}.rules.json`), «`scheme:*` в SchemeController» (теперь через CLI), «AINET как отдельный фасад» (теперь библиотека внутри aicli), «`image:download` пишет напрямую из AINET» (теперь bytes → CLI `content:store`), «`media:store/remove/list`» (теперь `content:store/remove/list`), «`www/media/<domain>/<allias>/<category>/`» (теперь `/content/<domain>/<allias>/<category>/`), «5 уровней карантина» (теперь 6), «whitelist 12 команд» (теперь 9 ролей + `*` = union + `*:list`).

Все эти утверждения исправлены в текущих версиях. Если встретите старую формулировку — доверяйте `MANIFEST.md`.

---

## Ключевые P0 (для быстрой ориентации)

- ~~CSRF на destructive GET.~~ ✅ Закрыто (stage-2b).
- ~~addslashes в компиляторах.~~ ✅ Закрыто (stage-1).
- ~~`Response::internalError($e->getMessage())` в prod.~~ ✅ Закрыто (stage-3).
- ~~Два формата `.edit.json` без миграции.~~ ✅ Закрыто (stage-2a).
- ~~`SchemeCompiler::str_replace('array (', '[')`.~~ ✅ Закрыто (stage-1).
- ~~Порт в Host не нормализуется.~~ ✅ Закрыто (stage-3).
- ~~RCE через custom/if блоки в визуальном редакторе.~~ ✅ Закрыто (удалены визуальные редакторы, добавлен флаг `allow_php_blocks`).

Все P0 закрыты. Подробнее — `MANIFEST.md` §7.1 и §10.

---

## Изменения в текущей итерации (page-rules unify + CLI audit + AINET + Content + Skin + Roles/Navigator)

### Page-rules unify + CLI audit

- ✅ Единая модель `.rules.json`: `algoritm_{name}.rules.json` для страниц (root — `algoritm/algoritm.rules.json`).
- ✅ Page-слой: PagePath, PageRules, PageNamespace — единый резолвинг путей/правил/namespace.
- ✅ **BREAKING:** `page:toggle --path` → `--name` + `--parent` (root через `--name=__root__`).
- ✅ `migrate:page-rules` — одноразовая миграция старых `.rules.json` на новую модель. Идемпотентна.
- ✅ `structure:check` — severity-aware (warning ≠ error, exit 0 при отсутствии errors).
- ✅ FileRouter — читает `algoritm_{name}.rules.json` на каждом шаге.
- ✅ Закрыт P0 в aicli: `page:write` теперь проверяет domain по `$allowedDomains`.

### CLI-аудит (39 команд)

- `scheme:copy/scheme:remove/scheme:save` — новые CLI-команды; SchemeController теперь тонкий клиент.
- `*:list` (6 команд: `domain:list`, `allias:list`, `page:list`, `scheme:list`, `block:list`, `filler:list`) + `--json` + `Result::$data`.
- Path traversal guard во всех командах с `--domain`/`--name`.
- realDomain: пути в `app/` строятся по `domains_real`, а не по входному хосту.
- Карантин для всех destructive-команд: `domain/allias/block/scheme/content/skin/trash`.
- `trash:clear` — обход **6 уровней** (`app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`), symlink-safe, флаги `--force/--older/--domain`.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- Полная очистка конфига: `domain:remove` (16 ключей), `allias:remove` (8 ключей).
- `filler:toggle` — idempotent, проверяет блок на диске.
- `db:migrate --dir` — path traversal guard.
- `admin:passwd` / `user:new` — валидация (login/password/email).
- `structure:repair` — root через PageCompiler, `algoritm.editor.json`, path traversal guard.
- `VarsExtractCommand` — удалён (мёртвый код).
- `content:store/remove/list` — команды content-хранилища (`/content/`).
- `skin:new/list/edit/remove` — команды скинов (`www/media/<domain>/<allias>/skin/`).

### Инфраструктура

- ✅ `Fs::rmrf` — единый рекурсивный метод (Windows-safe, symlink-safe), используется в `trash:clear` и Cache.
- ✅ PHPStan/CS-Fixer расширены на `bin/` и `www/` (269 файлов).
- ✅ Тесты: **570 тестов, 1671 assertion, 87 файлов**.

### AINET + Content + Skin

- Библиотека `src/Ainet/` (SSRF, HttpClient, Cache, ImageSearch, AinetFacade).
- UrlGuard: scheme/port/whitelist/private IP, ручные редиректы (`CURLOPT_FOLLOWLOCATION=false`).
- DomainWhitelist: точный матч, без wildcard, fail-safe (пустой = всё запрещено).
- HttpClient: GET-only, повторный UrlGuard на каждый Location.
- Cache: `sha256(url)` → file+meta, TTL 7 дней, `Fs::rmrf`.
- ImageSearch: wikimedia (default), openverse (fallback), автофоллбэк primary → next для default.
- AinetFacade: `searchImages`/`fetchJson`/`fetchText`/`downloadImage` (bytes в память, без записи).
- `bin/aicli.php`: 9 ролей (`src/Ai/Roles.php`), Navigator (`src/Ai/Navigator.php`), роль `*` = union всех ролей + специфичные, whitelist > 20 команд.
- `image:download` → AinetFacade (bytes) → `shell_exec` → `cli.php content:store`.
- `content:store` — запись в `/content/<domain>/<allias>/<category>/<hash>.<ext>` + `meta.json`, дедупликация. **Вне webroot.**
- `content:remove` — в карантин `/content/.trash/`, `--force`.
- `content:list` — список (путь, mime, размер, `page_refs`).
- `skin:new/list/edit/remove` — `www/media/<domain>/<allias>/skin/`. `skin:edit` — только `css/main.css` и `js/main.js`, 1 MB, блок `@import`/`javascript:`.
- `www/content.php` — front controller: GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`.
- `bin/ainet.php` — standalone-отладка (не в AI-whitelist).
- `config/ainet.config.example` + `_doc/ainet.md` — шаблон конфига и полный дизайн.
- Fallback: openverse `.engineering` → `.org`, `read_timeout 20s`, `default_provider = wikimedia`.

### Roles + Navigator

- ✅ `src/Ai/Roles.php` — 9 ролей: `parser`, `inspector`, `planner`, `architect`, `executor`, `designer`, `bundler`, `corrector`, `reporter`.
- ✅ `src/Ai/Navigator.php` — `next[]` HATEOAS, `OFFERABLE = [domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, done]`. `next[]` фильтруется по whitelist роли.
- ✅ `role='*'` = union всех ролей + специфичные (фикс регрессии, покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`).
- ✅ `agent_test.py` — рабочий E2E-оркестратор (Ollama ↔ aicli). `ai_agent/` (Planner→Builder→Content) — прототип (P3).

### Git-гигиена

- ✅ untrack `/content/` (`.gitignore`).
- ✅ untrack `app/damoony3test.ru/` (E2E sandbox, `.gitignore`).
- ✅ untrack `config/ainet.config`, `config/agent.config` (`.gitignore`).
- ✅ untrack `www/media/<domain>/<allias>/<category>/` (только `skin/` и `admin/`/`shared/` — git).