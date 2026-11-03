# Архитектура и контракты

Этот документ описывает внутреннее устройство Damoony Engine: принципы, слои, инварианты и открытые архитектурные ограничения. Инструкции по установке, примеры кода и справочник команд находятся в других документах.

---

## 1. 10 архитектурных принципов

1. **Домен и Алиас.** Домен (`app/[domain]/`) — инфраструктура. Алиас (`app/[domain]/[allias]/`) — контент. Строгое разделение.
2. **UI — это дерево.** Представление — типизированные узлы (`Node`, `Element`, `Attrs`), а не строки. XSS-экранирование на уровне дерева.
3. **Правило 3 файлов.** Любая сущность (Страница, Блок, Схема, Скин) состоит из `.php`, `.rules.json`, `.editor.json`.
4. **Файловый роутинг.** URL соответствует файловой иерархии `algoritm/`. Конфигурация — в `.rules.json`, не в `routes.php`.
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
   - **FileRouter** сопоставляет URL с файлами, читая `algoritm_[name].rules.json` на каждом шаге.
4. **PageHandler**: загружает `RegionRegistry`, подключает PHP-файл страницы, вызывает `default()`.
5. **Response**: если `default()` вернул `null`, отдаётся `204 No Content`. Если `Node` — рендерится в HTML. Ошибки в prod → generic 500.

*Важно:* PageHandler **не** оборачивает страницу в схему автоматически. Разработчик вызывает `SchemeLoader` + `SchemeRenderer` внутри `default()` (Вариант A) или использует `page:write --with_schema=1` (для AI).

---

## 3. Слои и структура данных

### 3.1. Правило 3 файлов
- **Страница:** `algoritm_[name].{php,rules.json,editor.json}` (все три в одной папке). Root: `algoritm/algoritm.{php,rules.json,editor.json}`.
- **Блок / Схема:** `[name].{php,rules.json,editor.json}` в `.alliasglobal/` или `.alliasscheme/`.

### 3.2. Content vs Skin (две разные зоны)
- **Content (`/content/[domain]/[allias]/[category]/`)** — runtime AI-контент. Вне webroot. Формат: `[hash].[ext]` + `[hash].meta.json`. Отдаётся через `www/content.php`. Запись только через CLI (`content:store`).
- **Skin (`www/media/[domain]/[allias]/skin/[name]/`)** — git-tracked инфраструктура. Файлы: `css/main.css`, `js/main.js`, `img/`, `skin.json`. Управление через CLI (`skin:*`).

### 3.3. Приоритет схем и Базовые Meta
- **Приоритет схем:** При рендеринге страницы `SchemeLoader` сначала ищет локальную схему алиаса (`{allias}.php`). И только если её нет — использует базовую (`www.php`).
- **Базовые Meta (CSS/JS):** Схемы содержат массив `meta`. При сборке финального `Document` эти данные автоматически вливаются в `<head>`.

### 3.4. Модульный CSS и Layout
- **Layout:** Базовые стили сетки (`.layout`, `.page-content`) и защита от переполнения картинок (`img { max-width: 100%; max-height: 500px; }`) жестко зашиты в `ai_agent/brain.py` и не могут быть сломаны LLM.
- **Модульность:** Вместо одного огромного `main.css`, стили генерируются по блокам: `main.css` (переменные), `top.css` (меню), `center.css` (контент). Блоки автоматически подключают свои CSS-файлы при рендеринге.
- **Защита от ИИ (Grid):** Сгенерированные сайты используют жестко зашитую CSS Grid-сетку (через `brain.py`). Для того чтобы обертки `<div>` от `SchemeRenderer` не ломали сетку, применяется CSS-хак `display: contents` для прямых потомков `.layout`.
- **Автогенерация стилей блоков:** При создании блока (`block:new`) движок автоматически создает CSS-файл для него в базовом скине (`www/media/{domain}/{allias}/skin/default/css/{block}.css`).

---

## 4. Ключевые контракты (интерфейсы)

- **DomainResolver:** `resolve(host): DomainContext`. Не бросает исключений. Возвращает `available=false`, если домен/алиас выключен. `real` — источник истины для путей в `app/`.
- **Router:** `match(path, context): RouteMatch`. Не рендерит ошибки, не выполняет контроллер. Возвращает `found=false` при промахе.
- **PageInterface:** `default(): Node|Response|null`. Базовый класс `Page` даёт `isAuthenticated()`, `user()`, `regions()`.
- **RegionInterface:** `render(RegionContext): Node`. Регион не знает о других регионах и о схеме.
- **ContentResponder:** `parse()`, `hostAllowed()`, `requiresAuth()`. Отдаёт только GET/HEAD. 404 при host-mismatch. 302 на логин при `page_refs` (auth).
- **Компиляторы (`PageCompiler`, `BlockCompiler`):** Принимают JSON-массив блоков (включая тип `nav` для меню) и генерируют безопасный PHP-код. Используют `Text::raw()` для вывода разрешенных тегов `<a>` и `<strong>` (защита от XSS остальных тегов).
- **AINET (`src/Ainet/`)**: `UrlGuard` (SSRF), `HttpClient` (GET-only, ручные редиректы), `AinetFacade` (bytes без записи на диск). URL whitelist независим от domain whitelist (`agent.config`).
- **Wizard (`src/Ai/`)**: `WizardManager` управляет строгим State Machine (init -> domain -> plan -> content -> commit). Отделяет валидацию черновиков от реального вызова CLI. `WizardSession` — DTO для хранения состояния (JSON в `var/wizard/`).

---

## 5. Инварианты (что всегда должно быть истинным)

- Домен ≠ Алиас.
- Ядро знает только `RegionInterface`.
- Все POST защищены CSRF. Destructive — только POST+CSRF (UI) или `--force` (CLI).
- Пароли — `password_hash`. Минимум 8 символов.
- `Document::release()` идемпотентен. `default(): null` возвращает 204 (не 500).
- Path traversal guard: все CLI-команды с `--domain`/`--name` проверяют `..` и regex.
- Карантин: destructive CLI-команды не удаляют сразу, а перемещают в `*_trash_[timestamp]/` (6 уровней).
- AI-Слой: единственная точка входа — `bin/aicli.php`. Роль `*` (default) — надмножество всех 9 ролей.
- AI не пишет сырой PHP. Используется безопасный JSON (PageCompiler/BlockCompiler).
- Content: запись только через CLI. AINET не пишет на диск.
- Skin: `skin:edit` разрешает только `css/main.css` и `js/main.js` (≤ 1 MB, без `@import`/`javascript:`).
- AINET: только GET, scheme (http/https), port (80/443), блок private IP. Whitelist без wildcard.

---

## 6. Открытые P2 (Технический долг)

*Список актуальных архитектурных компромиссов и известных недоработок.*

1. **AI-Транзакции:** нет отката (Rollback) при падении одной из команд в цепочке. Батчи для слабых моделей (Qwen 7B) — ручные.
2. **RegionContext::service():** возвращает `mixed` (Service Locator). Компенсируется `instanceof` на стороне вызывающего. Нужен `@template` в PHPDoc.
3. **assert() для типов:** в prod исчезает. Заменить на явные `if (!instanceof) throw`.
4. **Builder неполон:** нет методов `table`, `form`, `section`, `nav`, `label`, `select`, `textarea`.
5. **domains_real[$domain] в save():** работает только при self-mapping (P2 — заменить на `ConfigDomainResolver::real()`).
6. **PageHandler::applySkin:** читает `$_COOKIE` напрямую, а не через `LanguageNegotiator`-подобный сервис.
7. **Namespace Damoony\Algoritm:** сохранён (P2 — переименовать в `Damoony\Page`).
8. **AINET:** только GET (POST/PUT/DELETE запрещены). Нет парсинга HTML (только JSON API и текст).
9. **AI-Оркестраторы:** `ai_agent/brain.py` — рабочий мультиагент. UI для запуска из админки пока нет (только CLI/консоль).
10. **skin:bundle:** не реализован (сборка CSS/JS).
11. **Скрытые дефолты:** `Slot::release()` возвращает пустую строку (слот без региона молча исчезает).

---

## 7. Безопасность (архитектурные решения)

- **RCE:** удалены визуальные редакторы для страниц/блоков. `BlockCompiler` (единственный активный компилятор регионов) не поддерживает типы `custom`/`if`/`endif` — raw PHP в блоки через `block:write`/`filler:write` не попадает. `allow_php_blocks` остаётся только для `block:edit`/`filler:edit` (CLI-путь, отдельный флаг в `engine.config`, защита — см. `BlockEditCommand`/`FillerEditCommand`).
- **Path Traversal:** `str_contains('..')` + regex во всех CLI-командах.
- **SSRF:** `UrlGuard` (схема, порт, private IP, whitelist). `CURLOPT_FOLLOWLOCATION=false` + повторная проверка на каждый Location.
- **Карантин:** 6 уровней (`app/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/` и т.д.). Symlink-safe `Fs::rmrf`.
- **Content:** MIME whitelist, размер ≤ 10 MB, ≥ 200×200, дедупликация по sha256. Отдача с ETag, 304, SVG-CSP.
- **AI Whitelist:** два независимых списка — `agent.config` (домены для `app/*`) и `ainet.config` (URL для `net:*`).
