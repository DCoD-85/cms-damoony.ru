# Архитектура и контракты

Этот документ описывает внутреннее устройство Damoony Engine: принципы, слои, инварианты и открытые архитектурные ограничения. Инструкции по установке, примеры кода и справочник команд находятся в других документах.

---

## 1. 10 архитектурных принципов

1. **Домен и Алиас.** Домен (`app/[domain]/`) — инфраструктура. Алиас (`app/[domain]/[allias]/`) — контент. Строгое разделение.
2. **UI — это дерево.** Представление — типизированные узлы (`Node`, `Element`, `Attrs`), а не строки. XSS-экранирование на уровне дерева.
3. **Правило 3 файлов.** Любая сущность (Страница, Блок, Схема, Скин) состоит из `.php`, `.rules.json`, `.editor.json`.
4. **Файловый роутинг.** URL соответствует файловой иерархии `algoritm/`. Конфигурация — в `.rules.json`, не в `routes.php`. Auth — не часть роутинга: `.rules.json:auth` — метаданные, enforcement — в коде страницы (см. §5).
5. **Low-Code только для Схем.** Визуальный Drag&Drop оставлен только для каркасов (Схем). Страницы и блоки пишутся кодом (CodeMirror) или AI (безопасный JSON).
6. **Explicit over implicit.** Без `$GLOBALS`, магии `__get`, скрытых дефолтов. Всё передаётся явно через конструктор.
7. **Contract-first.** Публичный API — интерфейсы (`RegionInterface`, `PageInterface`, `ProviderInterface`), а не конкретные классы.
8. **CLI-First.** CLI (`bin/cli.php`) — единственный источник истины для файловых операций. Админка и AI — тонкие клиенты над CLI.
9. **Безопасность — основа.** Никакого сырого PHP из UI/AI, CSRF на всех POST, карантин для удаления, SSRF-защита в сети, Path Traversal guards.
10. **Диагностика.** `StructureChecker` проверяет целостность (наличие 3 файлов, стыковку филлеров). Ошибки severity-aware (error/warning).

---

## 2. Жизненный цикл HTTP-запроса

1. **Kernel** принимает `Request`. Оборачивает выполнение в `try/catch` (белых экранов нет).
2. **Middleware** (стек): `SecurityHeadersMiddleware` (CSP/HSTS) → `RateLimitMiddleware` → `LanguageCookieMiddleware` → `SkinCookieMiddleware`.
3. **FrontController**: 
   - `DomainResolver` определяет `DomainContext` (нормализует Host, отрезает порт).
   - Проверяет доступность домена/алиаса (если выключен — 503).
   - **Принимает `?string $sessionId = null` и `?string $aliasOverride = null`** (для AIUI-превью из `.sess_*` и переопределения алиаса в URL `/admin/preview/{tab}/{alias}/...`).
   - Если `$aliasOverride !== null` → `$domain = $domain->withAllias($aliasOverride)`.
   - **FileRouter** сопоставляет URL с файлами, читая `algoritm_[name].rules.json` на каждом шаге. При `$sessionId !== null` base-path = `app/.sess_{tab}.{domain}/{alias}/algoritm/`, иначе — `app/{domain}/{alias}/algoritm/`.
   - **`PageHandler`** получает `session_id` через `RegionContext::service('session_id')` и читает PHP из `.sess_*` при наличии сессии.
4. **PageHandler**: загружает `RegionRegistry`, подключает PHP-файл страницы, вызывает `content()`.
5. **Response**: если `content()` вернул `null`, отдаётся `204 No Content`. Если `Node` — рендерится в HTML. Ошибки в prod → generic 500.



---

## 3. Слои и структура данных

### 3.1. Модульная структура

`src/` разделён на 9 модулей. Каждый модуль решает только свою задачу.

| Модуль | Путь | Ответственность |
|---|---|---|
| **Core** | `src/Core/` | Ядро: HTTP (Kernel, Request, Response), Роутинг (FileRouter), Домены, Файлы, Безопасность (Csrf), Сессии, I18n. |
| **UI** | `src/UI/` | Всё для отрисовки: `Element`, `Builder`, `Page`, `Region`, `Scheme`, `Skin`, `Components`. |
| **Data** | `src/Data/` | Работа с БД: `Connection`, `DataProvider`, `Auth`. |
| **Cli** | `src/Cli/` | Все консольные команды: `Commands/` (для `bin/cli.php`), `Db/` (для `bin/dbcli.php`), `Ai/` (для `bin/aicli.php`). |
| **Admin** | `src/Admin/` | `Auth.php`, `AuthInterface.php` — авторизация админки. Страницы админки — `Admin\\Pages\\{role}` (через `FileRouter`). |
| **Ai** | `src/Ai/` | Мультиагентный Orchestrator, ModelRouter, Roles, Navigator. |
| **Ainet** | `src/Ainet/` | Выход в интернет: `UrlGuard` (SSRF), `HttpClient`, `AinetFacade`, ImageSearch-провайдеры. |
| **Diagnostics** | `src/Diagnostics/` | `StructureChecker` — целостность структуры (error/warning). Используется в `structure:check` и админке. |
| **Demo** | `src/Demo/` | Legacy-примеры страниц/регионов. Используется точечно (`app/damoony.ru/www/algoritm/ajax/`). |

**Правило:** если код нужен и сайту, и админке, и CLI — он живёт в `Core` или `UI`. Если только CLI — в `Cli`.

### 3.2. Правило 3 файлов
- **Страница:** `algoritm_[name].{php,rules.json,editor.json}` (все три в одной папке). Root: `algoritm/algoritm.{php,rules.json,editor.json}`.
- **Блок / Схема:** `[name].{php,rules.json,editor.json}` в `.blocksglobal/` (глобальные) или `.blockslocal/` (локальные). Схемы: `.schemesglobal/` или `.schemeslocal/`.
- **Приоритет:** `.blockslocal/{name}/{name}.php` перекрывает `.blocksglobal/{name}/{name}.php`. Локальный класс: `blockslocal_{alias}_{name}`, глобальный: `blocksglobal_{name}`.

### 3.3. Content vs Skin (две разные зоны)
- **Content (`/content/[domain]/[allias]/[category]/`)** — runtime AI-контент. Вне webroot. Формат: `[hash].[ext]` + `[hash].meta.json`. Отдаётся через `www/content.php`. Запись только через CLI (`content:store`).
- **Skin (`www/media/[domain]/[allias]/skin/[name]/`)** — git-tracked инфраструктура. Файлы: `css/main.css`, `js/main.js`, `img/`, `skin.json`. Управление через CLI (`skin:*`).

### 3.4. Приоритет схем и Базовые Meta
- **Приоритет схем:** `SchemeLoader` для public-страницы ищет `{allias}.php` в `app/{domain}/.schemesglobal/`, затем `{schemeName}.php` (`schemeName` — из `domains_scheme[real]`, по умолчанию `www`). Для admin-страницы — `{role}/.schemesglobal/{allias}.php` → `{role}/.schemesglobal/admin.php` → `.admin/.schemesglobal/{allias}.php` → `.admin/.schemesglobal/admin.php` (role-level override → domain-level).
- **Базовые Meta (CSS/JS):** Схемы содержат массив `meta`. При сборке финального `Document` эти данные автоматически вливаются в `<head>`. Пути в `meta.css`/`meta.js` могут содержать токены `{skin}`, `{real}`, `{alias}` — `SchemeRenderer` подставляет активный skin и домен/алиас.

### 3.5. CSS и Layout (факты)
- **Layout:** Базовые стили сетки (`.layout`, `.page-content`) и защита от переполнения картинок (`img { max-width: 100%; max-height: 500px; }`) жестко зашиты в `ai_agent/brain.py` и не могут быть сломаны LLM.
- **Факт (2026-11-06):** `block:new` CSS не создаёт. `brain.py` (Wizard) пишет два файла: `main.css` (base + layout + center) и `top.css` (меню). Отдельного `center.css` нет.
- **Защита от ИИ (Grid):** Сгенерированные сайты используют жестко зашитую CSS Grid-сетку (через `brain.py`). Для того чтобы обертки `<div>` от `SchemeRenderer` не ломали сетку, применяется CSS-хак `display: contents` для прямых потомков `.layout`.
- **Автогенерация стилей блоков:** НЕ реализована. `BlockCompiler` подключает `{blockName}.css` по имени блока, если файл существует (404 игнорируется).

---

## 4. Ключевые контракты (интерфейсы)

- **DomainResolver:** `resolve(host): DomainContext`. Не бросает исключений. Возвращает `available=false`, если домен/алиас выключен. `real` — источник истины для путей в `app/`.
- **Router:** `match(path, context): RouteMatch`. Не рендерит ошибки, не выполняет контроллер. Возвращает `found=false` при промахе.
- **PageInterface:** `content(): Node|Response|null`. Базовый класс `Page` даёт `isAuthenticated()`, `user()`, `regions()`. `AdminPage` проверяет `Admin\AuthInterface::isLoggedIn()` в `guard()` (при `false` → 302 на `/admin/{default_lang}/login`, `default_lang` из `admin.config`).
- **RegionInterface:** `render(RegionContext): Node`. Регион не знает о других регионах и о схеме.
- **SkinManager (`src/UI/Skin/SkinManager.php`):** `list(real, alias): list<string>` — скины в `www/media/{real}/{alias}/skin/`. `exists(real, alias, skin): bool` — проверка папки. Используется в `SchemeRenderer` для fallback при подстановке `{skin}`.
- **ContentResponder:** `parse()`, `hostAllowed()`, `requiresAuth()`. Отдаёт только GET/HEAD. 404 при host-mismatch. 302 на логин при `page_refs` (auth).
- **Компиляторы (`PageCompiler`, `BlockCompiler`):** Принимают JSON-массив блоков (включая тип `nav` для меню) и генерируют безопасный PHP-код. Используют `Text::raw()` для вывода разрешенных тегов `<a>` и `<strong>` (защита от XSS остальных тегов).
- **AINET (`src/Ainet/`)**: `UrlGuard` (SSRF), `HttpClient` (GET-only, ручные редиректы), `AinetFacade` (bytes без записи на диск). URL whitelist независим от domain whitelist (`agent.config`).
- **Wizard (`src/Ai/`)**: `WizardManager` управляет строгим State Machine (`init → domain → allias → plan → content → skin → ready → committed`, см. `WizardSession::STAGE_*`). Отделяет валидацию черновиков от реального вызова CLI. `WizardSession` — DTO для хранения состояния (JSON в `var/wizard/`). Используется внешним `brain.py`; **не связан** с внутренним Orchestrator. `wizard:abort` заявлен в `Roles.php`, но в `WizardManager::handle()` не реализован — при вызове вернёт `Unknown wizard action`. `wizard:widgets` — реализован, но отсутствует в whitelist ролей.
- **Orchestrator (`src/Ai/Orchestrator/`)** — внутренний мультиагентный конвейер для AIUI:
  - `ContextManager` — Blackboard (чтение/запись `.sess_*_/context.json`): `meta`, `input` (prompt, brief, current_blocks, history), `tools_context`, `agent_outputs`.
  - `ToolsCollector` — read-only снимок окружения (`structure:check`, `db:schema`) через `AicliClient::runJson()`.
  - `Orchestrator::run()` — интерактивный режим (Interviewer -> chat/architect).
  - `Orchestrator::build()` — пакетный режим по кнопке «Создать»: Architect -> per-page Coder -> `page:new`/`page:write`. Пишет `meta.build_progress` (per-page status).
  - **Agents** (`src/Ai/Orchestrator/Agent/`): `InterviewerAgent` (brief accumulation через `gemma4:e4b`), `ArchitectAgent` (возвращает `{pages: [...]}` через `deepseek-coder`), `CoderAgent` (per-page JSON-блоки + strip markdown).
  - **ModelRouter (`src/Ai/ModelRouter.php`)** — читает `config/models.config` (роль -> модель). Приоритет: `args['model']` -> `ModelRouter::resolve($role)` -> `agent.config.llm_model`.
  - **Отличие от `bin/aicli.php`**: Orchestrator = внутренний API для `ai_edit.php` (вызывает `AicliClient`, а не `bin/aicli.php`); aicli = внешний JSON-in/out для `brain.py` и сторонних агентов.

---

## 5. Инварианты (что всегда должно быть истинным)
- Домен ≠ Алиас.
- **FS-пути — только от `realDomain`.** `Paths` принимает realDomain. CLI-команды резолвят через `ResolvesRealDomain`.
- **Структура v4 (по умолчанию для новых):** `domains_structure[real] = 'v4'`. Разделение на `global` (домен) и `local` (алиас): `.blocksglobal`/`.blockslocal`, `.dbglobal`/`.dblocal`, `.i18nglobal`/`.i18nlocal`.
- **Schema-First.** Если `Page::content()` возвращает `null`, `PageHandler` всё равно рендерит схему (`SchemeRenderer`). Пустая страница (204) отдается только если нет ни схемы, ни контента.
- **CSRF обязателен для всех POST.** `PageHandler` проверяет токен `_csrf` (через `Csrf::check()`) для любого POST-запроса. При ошибке — 403.
- **Auth — ответственность страницы.** `AdminPage::guard()` проверяет `AuthInterface::isLoggedIn()`.
- **i18n:** Плоские JSON-ключи (`site.block.top`). Fallback: `local` перекрывает `global`. Метод `tPlural()` для склонений.
- **Сборки (Assemblies).** `bin/cli.php new --domain=X --assembly=developer` разворачивает структуру из `stubs/assemblies/`. Никаких хардкодов структуры в PHP-командах.
- **AI не пишет сырой PHP.** Используется безопасный JSON (`PageCompiler`/`BlockCompiler`).
- **DataProvider — только через `bin/aicli.php`/`brain.py`.** CLI работает с БД через `dbcli` и `db:*`.

## 6. Открытые P2 (Технический долг)

14 пунктов. Развёрнуто — `git log -p _doc/arch.md`, `git log -p _doc/orchestrator.md`, issues.

1. **AI-Транзакции:** нет `build:rollback`; failed-страницы остаются в `build_progress`.
2. **RegionContext::service():** возвращает `mixed`; нужен `@template` PHPDoc.
3. ~~**assert():** в prod исчезает; заменить на явные `if (!instanceof) throw`.~~ Решено: заменено на `if (!instanceof) throw new \RuntimeException(...)` в `AppFactory.php` и `FrontController.php` (7 мест).
4. ~~**Builder неполон:**~~ **Реверт:** методы `form`/`table`/`nav`/`select`/`textarea`/`label` добавлены в `74edf81`, но удалены в `0935c61`. Текущий `Builder` — 12 Element-фабрик (`el`, `elA`, `div`, `span`, `p`, `h`, `a`, `img`, `input`, `ul`, `li`, `button`). Композиты — в `src/UI/Components/*`.
5. **domains_real[$domain] в save():** работает только при self-mapping.
6. **Namespace `Damoony\UI\Algoritm`:** унаследован; P2 — переименовать в `Damoony\UI\Page`.
7. **AINET:** только GET; нет парсинга HTML.
8. **ArchitectAgent нестабилен:** разные имена страниц в разных запусках; P2 — whitelist slug-ов.
9. **Coder wrap в `section`:** `build_progress.blocks` считает только верхний уровень.
10. **`PageNewCommand --session`:** требует рестарт php-cgi (opcache) на Windows.
11. **Designer (CSS) в AIUI отсутствует:** только `brain.py`. AIUI использует skin по умолчанию.
12. **Markdown в Coder:** `stripMarkdown()` покрывает не всё (списки, цитаты, таблицы).
13. **skin:bundle:** не реализован.
14. **Скрытые дефолты:** `Slot::release()` возвращает пустую строку. План: маркер в dev, пусто в prod. См. dev.md §12 (шаг E).

## 7. Безопасность (архитектурные решения)

- **RCE:** удалены визуальные редакторы для страниц/блоков. `BlockCompiler` (единственный активный компилятор регионов) не поддерживает типы `custom`/`if`/`endif` — raw PHP в блоки через `block:write`/`filler:write` не попадает. `allow_php_blocks` остаётся только для `block:edit`/`filler:edit` (CLI-путь, отдельный флаг в `engine.config`, защита — см. `BlockEditCommand`/`FillerEditCommand`).
- **Path Traversal:** `str_contains('..')` + regex во всех CLI-командах.
- **SSRF:** `UrlGuard` (схема, порт, private IP, whitelist). `CURLOPT_FOLLOWLOCATION=false` + повторная проверка на каждый Location.
- **Карантин:** 6 уровней (`app/`, `.schemesglobal/`, `.blocksglobal/`, `/content/.trash/`, `www/media/.trash/` и т.д.). Symlink-safe `Fs::rmrf`.
- **Content:** MIME whitelist, размер ≤ 10 MB, ≥ 200×200, дедупликация по sha256. Отдача с ETag, 304, SVG-CSP.
- **AI Whitelist:** два независимых списка — `agent.config` (домены для `app/*`) и `ainet.config` (URL для `net:*`).
- **Forms (`www/forms.php`):** публичный POST-endpoint для отправки форм с сайта (Phase 4.4). CSRF-токен в форме, rate-limit на IP, валидация полей по `name`. Записи в `form_submissions` (без `user_id`, с `ip`, `created_at`). Nginx: `location = /submit { rewrite ^ /forms.php last; }` (см. `install.md` §5).
- **DataProvider write (`db:mutate`):** только с `--force`, каждая мутация пишется в `audit_log` (provider, method, params_json, result_ok, error_text, role_name, source=cli). Read-only `db:call` использует whitelist шаблонов (см. `cli.md` §11). CREATE/DROP/ALTER — запрещены всем ролям, миграции только `db:migrate` от владельца.
