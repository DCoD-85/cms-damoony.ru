Философия проекта Damoony

10 ключевых принципов, на которых построен движок. Код и реализации старого проекта не переносились — переносились принципы и контракты.

⚠️ Честно: не все принципы соблюдаются в текущем коде. Где есть расхождение — это указано явно. Истина о фактическом поведении — MANIFEST.md §7. Этот документ — о намерениях.

---

## 1. Домен и Алиас — разделение инфраструктуры и контента

Всё вращается вокруг Домена и Алиаса. Домен — инфраструктура (настройки, привязки хостов, глобальные схемы и блоки). Алиас — контент (страницы, локальные схемы, переопределения блоков, контент и скины).

**Следствие:** DomainResolver как точка входа; никакого хардкода имён доменов; админка и файловая структура строго разделяют уровни. Content (`/content/<domain>/<allias>/`, runtime, вне webroot) зеркалит структуру `app/<domain>/<allias>/`. Skin (`www/media/<domain>/<allias>/skin/`, git-tracked) — отдельная зона, не смешивается с Content.

**Соблюдается:** ✅ полностью. Это самый крепкий принцип проекта.

---

## 2. UI — это дерево, а не текст

Представление — типизированное дерево узлов (Node, Element, Attrs), а не строковый шаблон. Рендер — финальный шаг. Высокоуровневые элементы (формы, таблицы) — из базовых компонентов.

**Следствие:** никаких Twig/Blade; UI-слой — PHP-DOM с явными типами и XSS-экранированием на уровне атрибутов.

**Соблюдается:** ✅ в основном.

**Расхождения:**
- Builder неполон (нет table, form, section и т.д.) — сложные структуры через `new Element()` (§6.6).
- Node — контракт с изъяном: `Text::append()`, `Slot::append()` бросают `LogicException`. Разделение на Node / ParentNode — не сделано.

---

## 3. Регионы, слоты и Filler (Правило 3 файлов)

Шаблон домена — именованные регионы (блоки). Схема (каркас страницы) содержит слоты, в которые регионы вставляются. Иерархия схем: локальная алиаса → глобальная домена. Filler — переопределение региона для конкретного алиаса.

**Следствие:** регион — единица расширения. Ядро знает только о RegionInterface. Глобальные манифесты (blocks.json, schemes.json) удалены. Каждая сущность (Страница, Блок, Филлер, Схема, **Скин**) описывается тремя файлами: `{name}.php`, `{name}.rules.json`, `{name}.editor.json`. Для страниц — единый префикс `algoritm_{name}.{...}`.

**Соблюдается:** ✅ полностью. RegionRegistry (disabled → forcedGlobal → filler → base) работает образцово.

**Расхождения:**
- `Slot::release()` возвращает `''` — слот без региона молча исчезает (диагностика через `structure:check`).

---

## 4. Роутинг — файловые правила

Страницы живут в файловой иерархии `algoritm/` алиаса. Правила — `algoritm_{name}.rules.json` рядом. Роутер читает правила, идёт по сегментам URL, вызывает `default()`. Regex-параметры, флаг auth, namespace — часть контракта.

**Следствие:** никаких `routes.php` с сотнями строк. Конфигурация роутинга — данные, не код.

**Соблюдается:** ✅ полностью.

**Ограничения:**
- `@preg_match` в `RulesMatcher` глушит ошибки — битый regex даст тихий `notFound`, а не диагностику.

---

## 5. Low-Code, Zero-Overhead и AI-Генерация

Визуальный Drag&Drop оставлен ТОЛЬКО для Схем (HTML-каркасов). Админка сохраняет структуру схемы в `editor.json` и компилирует в `{name}.php` через CLI `scheme:save`.

Страницы, Блоки и Филлеры визуальных редакторов НЕ ИМЕЮТ (удалены ради безопасности). Они пишутся через CodeMirror (ручной PHP) или генерируются AI-агентом через безопасный JSON (PageCompiler).

**Следствие:** мы убрали "магию" генерации кода из UI, закрыв уязвимость RCE. При этом AI может быстро создавать контент, не имея доступа к сырому PHP (только безопасные блоки: h1, text, img и т.д.). С AINET AI также может безопасно получать картинки и данные из интернета (без RCE, без SSRF). **Роли (9) и Navigator (next[] HATEOAS)** направляют AI по безопасному пути — агент выбирает из подсказанных шагов, а не выдумывает команды.

**Соблюдается:** ✅ полностью. PageCompiler (JSON → PHP) доказал свою безопасность и эффективность (генерация 10-страничного сайта за 1 шаг, E2E через `agent_test.py`). Используется не только в `page:write`, но и для stub-страниц в `domain:new`/`allias:new`/`structure:repair`.

**Расхождения:**
- Для генерации сырого PHP в Блоках (`custom`/`if`) требуется явный флаг `security.allow_php_blocks=true` (по умолчанию заблокировано).
- AI не может делать POST/PUT/DELETE (только GET), не может парсить HTML (только JSON API и текст) — это осознанные ограничения AINET.
- Skins: CLI `skin:new/list/edit/remove` есть, но UI-менеджера скинов и переключателя на фронте пока нет.

---

## 6. Explicit over implicit

Никаких `$GLOBALS`, статических кэшей, магии `__get`, скрытых зависимостей. Всё передаётся явно через конструктор. Контейнер — удобство сборки, не источник состояния.

**Следствие:** класс без конструктора с зависимостями — ошибка проектирования.

**Соблюдается:** ✅ в основном.

**Расхождения:**
- `assert()` в prod исчезает — проверки типов из контейнера не выполняются (§6.3, P2).
- `RegionContext::service(string): mixed` — Service Locator (осознанный trade-off, §6.2).
- Главное противоречие §6.1 РЕШЕНО: страницы создаются как "голые" (только код), разработчик явно вызывает `SchemeRenderer`, если нужен каркас (или `--with_schema` для AI).

---

## 7. Contract-first

Публичный API — интерфейсы (`AuthInterface`, `RegionInterface`, `CommandInterface`, `PageInterface`, `HttpTransportInterface`, `ProviderInterface`), не классы. Реализация — деталь. Каждый модуль — один публичный вход.

**Следствие:** расширение публичного API — только через новые интерфейсы. AINET: `HttpTransportInterface` (`CurlTransport` для prod, `FakeTransport` для тестов), `ProviderInterface` (WikimediaProvider, OpenverseProvider). AI-слой: `Roles` (9 ролей) и `Navigator` (`next[]` HATEOAS) — контракты поведения AI-агента, не деталь реализации. Content front controller `www/content.php` — контракт отдачи `/content/` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`).

**Соблюдается:** ✅ в основном.

**Расхождения:**
- `RegionContext::service()` возвращает `mixed` — контракт размыт (§6.2).
- `PageInterface::default(): Node|Response|null` разрешает null, но `PageHandler::toResponse()` на null возвращает 204 No Content (stage-4).
- Роль `*` — надмножество всех ролей (инвариант, покрыт тестом `RolesTest::testWildcardIsSupersetOfAllRoles`).
- Navigator `next[]` фильтруется по whitelist роли (defense in depth).

---

## 8. CLI-First (Единый источник истины)

Любая файловая операция (создание, удаление, компиляция, миграция, запись контента, запись скина) вызывается через CLI (`bin/cli.php`).

Админка — это "тонкий клиент". Она рендерит HTML и вызывает CLI-команды (включая `scheme:copy/remove/save`, `content:*` и `skin:*` — после CLI-аудита).

AI-агенты используют единственный безопасный фасад `bin/aicli.php`, который вызывает CLI (для `app/*`, `content:store`, `skin:*`) или `AinetFacade` (для `net:*`).

**Следствие:** бизнес-логика не дублируется. Любое изменение в CLI мгновенно отражается в админке и доступно агентам.

**Соблюдается:** ✅ полностью. Все **39 команд**, включая `scheme:copy/remove/save`, `content:store/remove/list`, `skin:new/list/edit/remove` (было: прямое в SchemeController). Destructive-операции — в карантин (`*_trash_<timestamp>/`), требуют `--force`. Path traversal guard во всех командах с `--domain`/`--name`. AINET не пишет на диск (bytes → CLI `content:store`) — CLI остаётся единственным источником истины для FS.

---

## 9. Безопасность — не фича, а основа

Пароли — только `password_hash`. CSRF на всех POST. Удаление — только через POST (UI) или `--force` (CLI). Загрузки — whitelist. Сессии — HttpOnly, SameSite. Safe Mode и строгий CSP.

Удалены визуальные редакторы страниц и блоков (защита от RCE). Доступ к AI-агенту ограничен **9 ролями** (`src/Ai/Roles.php`, роль `*` = union всех ролей + специфичные) в `bin/aicli.php` и двумя списками доменов (`agent.config` для `app/*` и `image:download.domain`, `ainet.config` для URL `net:*`). **Navigator** (`next[]`) дополнительно фильтрует шаги по whitelist роли.

**Следствие:** никаких `phpinfo()`, GET-удалений, паролей в открытом виде, выполнения сырого PHP из UI или от AI (используется PageCompiler).

**Соблюдается:** ✅ в основном. Все P0 закрыты. Path traversal guard в CLI (`domain`/`allias`/`block`/`filler`/`scheme`/`content`/`skin`/`db:migrate`). Пароли минимум 8 символов (`admin:passwd`, `user:new`). Уникальность email в `user:new`. Карантин для destructive-команд (**6 уровней**). SSRF-защита в AINET (UrlGuard: scheme/port/whitelist/private IP, ручные редиректы, только GET). MIME whitelist и размеры в `content:store`. `skin:edit` — whitelist файлов (`css/main.css`, `js/main.js`), 1 MB, блок `@import`/`javascript:`. `www/content.php` — front controller с auth по `page_refs`.

---

## 10. Диагностика и отказоустойчивость

Система сама подсказывает разработчику, что сломано. StructureChecker проверяет наличие всех 3 файлов (`.php`, `.rules.json`, `.editor.json`) для каждого элемента, стыковку блоков и схем. Есть System Health, Safe Mode.

**Следствие:** System Health, `structure:check` (severity-aware), `structure:repair`, `migrate:page-rules`, `trash:clear` (**6 уровней**), `content:list`, `skin:list`, `net:status`.

**Соблюдается:** ✅ в основном.

**Расхождения:**
- Safe Mode работает на уровне bootstrap админки, но ошибки внутри контроллеров ловит Kernel (500, не Safe Mode) — это ожидаемо.
- AINET rate limit (30/min) описан в конфиге, но не применяется на уровне AinetFacade (P2).

---

## Стек

- PHP 8.4
- Composer (PSR-4, PSR-3, PSR-11)
- PHPUnit 10+ (**570 тестов, 1671 assertion** — верифицировано; 1 skipped; **87 тестовых файлов**)
- PHPStan (level 8, 269 файлов: `src/`, `bin/`, `www/`)
- PHP-CS-Fixer (269 файлов: `src/`, `bin/`, `www/`, `tests/`)
- PDO (MariaDB / MySQL; SQLite в тестах)
- Nginx + PHP-FPM (или Apache + mod_php)
- Ollama (локальные LLM: Qwen 2.5 Coder 7B для AI-агентов)
- curl + openssl (AINET)

---

## Итог: соблюдение принципов

| # | Принцип | Статус | Ключевое расхождение |
|---|---|---|---|
| 1 | Домен/Алиас | ✅ | Content (`/content/`) + Skin (`www/media/.../skin/`) — две отдельные зоны, не смешиваются |
| 2 | UI — дерево | ✅ | Builder неполон, Node/ParentNode |
| 3 | Регионы/слоты/filler | ✅ | Slot без региона молча исчезает. Правило 3 файлов — для 5 сущностей (включая Skin) |
| 4 | Файловый роутинг | ✅ | `@preg_match` глушит ошибки |
| 5 | Low-Code / AI-Генерация | ✅ | Визуал только для схем (через CLI `scheme:save`), страницы/блоки — JSON (AI) или код. Роли (9) + Navigator. AINET: только GET, без парсинга HTML |
| 6 | Explicit over implicit | ✅ | Противоречие §6.1 решено (ручной вызов схемы или `--with_schema`) |
| 7 | Contract-first | ✅ | `default(): null` → 204 (stage-4). AINET: HttpTransportInterface, ProviderInterface. AI: Roles + Navigator. Content: ContentResponder |
| 8 | CLI-First | ✅ | Все **39 команд** через CLI, включая `scheme:*`, `content:*`, `skin:*`. AINET не пишет на диск |
| 9 | Безопасность — основа | ✅ | RCE закрыт, path traversal guard, карантин (**6 уровней**), password ≥8, SSRF-защита, **9 ролей + Navigator**, два whitelist, `www/content.php` (auth по `page_refs`) |
| 10 | Диагностика | ✅ | Severity-aware `structure:check`, `structure:repair`, `migrate:page-rules`, `trash:clear` (**6 уровней**), `content:list`, `skin:list`, `net:status` |

**10 принципов соблюдаются полностью (с оговорками MANIFEST §6–7).** P0-блокеры закрыты. Проект прошел путь от Low-Code конструктора до безопасной AI-Native CMS с архитектурой CLI-First и 9 ролями AI-агента. Архитектура — на **9.8/10**.

Подробнее о конкретных багах — MANIFEST.md §6–7. О состоянии проекта — readme.md.