Архитектура и контракты
Этот документ описывает внутреннее устройство Damoony Engine: принципы, слои, инварианты и открытые архитектурные ограничения. Инструкции по установке, примеры кода и справочник команд находятся в других документах.

1. 10 архитектурных принципов
Домен и Алиас. Домен (app/[domain]/) — инфраструктура. Алиас (app/[domain]/[allias]/) — контент. Строгое разделение.

UI — это дерево. Представление — типизированные узлы (Node, Element, Attrs), а не строки. XSS-экранирование на уровне дерева.

Правило 3 файлов. Любая сущность (Страница, Блок, Схема, Скин) состоит из .php, .rules.json, .editor.json.

Файловый роутинг. URL соответствует файловой иерархии algoritm/. Конфигурация — в .rules.json, не в routes.php.

Low-Code только для Схем. Визуальный Drag&Drop оставлен только для каркасов (Схем). Страницы и блоки пишутся кодом (CodeMirror) или AI (безопасный JSON).

Explicit over implicit. Без $GLOBALS, магии __get, скрытых дефолтов. Всё передаётся явно через конструктор.

Contract-first. Публичный API — интерфейсы (RegionInterface, PageInterface, ProviderInterface), а не конкретные классы.

CLI-First. CLI (bin/cli.php) — единственный источник истины для файловых операций. Админка и AI — тонкие клиенты над CLI.

Безопасность — основа. Никакого сырого PHP из UI/AI, CSRF на всех POST, карантин для удаления, SSRF-защита в сети, Path Traversal guards.

Диагностика. StructureChecker проверяет целостность (наличие 3 файлов, стыковку филлеров). Ошибки severity-aware (error/warning).

2. Жизненный цикл HTTP-запроса
Kernel принимает Request. Оборачивает выполнение в try/catch (белых экранов нет).

Middleware (стек): SecurityHeadersMiddleware (CSP/HSTS) → RateLimitMiddleware → LanguageCookieMiddleware → SkinCookieMiddleware.

FrontController:

DomainResolver определяет DomainContext (нормализует Host, отрезает порт).

Проверяет доступность домена/алиаса (если выключен — 503).

FileRouter сопоставляет URL с файлами, читая algoritm_[name].rules.json на каждом шаге.

PageHandler: загружает RegionRegistry, подключает PHP-файл страницы, вызывает default().

Response: если default() вернул null, отдаётся 204 No Content. Если Node — рендерится в HTML. Ошибки в prod → generic 500.

Важно: PageHandler не оборачивает страницу в схему автоматически. Разработчик вызывает SchemeLoader + SchemeRenderer внутри default() (Вариант A) или использует page:write --with_schema=1 (для AI).

3. Слои и структура данных
3.1. Правило 3 файлов
Страница: algoritm_[name].{php,rules.json,editor.json} (все три в одной папке). Root: algoritm/algoritm.{php,rules.json,editor.json}.

Блок / Схема: [name].{php,rules.json,editor.json} в .alliasglobal/ или .alliasscheme/.

3.2. Content vs Skin (две разные зоны)
Content (/content/[domain]/[allias]/[category]/) — runtime AI-контент. Вне webroot. Формат: [hash].[ext] + [hash].meta.json. Отдаётся через www/content.php. Запись только через CLI (content:store).

Skin (www/media/[domain]/[allias]/skin/[name]/) — git-tracked инфраструктура. Файлы: css/main.css, js/main.js, img/, skin.json. Управление через CLI (skin:*).

3.3. Приоритет схем и Базовые Meta
Приоритет схем: При рендеринге страницы SchemeLoader сначала ищет локальную схему алиаса ({allias}.php). И только если её нет — использует базовую (www.php). Это позволяет разным алиасам (например, docs) иметь уникальную верстку.

Базовые Meta (CSS/JS): Схемы содержат массив meta. При сборке финального Document эти данные автоматически вливаются в <head>. По умолчанию scheme:new записывает туда общие стили из /media/shared/.

Автогенерация стилей блоков: При создании блока (block:new) движок автоматически создает CSS-файл для него в базовом скине (www/media/{domain}/www/skin/default/css/{block}.css) и подключает его в PHP-классе блока через $context->meta->addCss().

4. Ключевые контракты (интерфейсы)
DomainResolver: resolve(host): DomainContext. Не бросает исключений. Возвращает available=false, если домен/алиас выключен. real — источник истины для путей в app/.

Router: match(path, context): RouteMatch. Не рендерит ошибки, не выполняет контроллер. Возвращает found=false при промахе.

PageInterface: default(): Node|Response|null. Базовый класс Page даёт isAuthenticated(), user(), regions().

RegionInterface: render(RegionContext): Node. Регион не знает о других регионах и о схеме.

ContentResponder: parse(), hostAllowed(), requiresAuth(). Отдаёт только GET/HEAD. 404 при host-mismatch. 302 на логин при page_refs (auth).

AINET (src/Ainet/): UrlGuard (SSRF), HttpClient (GET-only, ручные редиректы), AinetFacade (bytes без записи на диск). URL whitelist независим от domain whitelist (agent.config).

5. Инварианты (что всегда должно быть истинным)
Домен ≠ Алиас.

Ядро знает только RegionInterface.

Все POST защищены CSRF. Destructive — только POST+CSRF (UI) или --force (CLI).

Пароли — password_hash. Минимум 8 символов.

Document::release() идемпотентен. default(): null возвращает 204 (не 500).

Path traversal guard: все CLI-команды с --domain/--name проверяют .. и regex.

Карантин: destructive CLI-команды не удаляют сразу, а перемещают в *_trash_[timestamp]/ (6 уровней).

AI-Слой: единственная точка входа — bin/aicli.php. Роль * (default) — надмножество всех 9 ролей.

AI не пишет сырой PHP. Используется безопасный JSON (PageCompiler).

Content: запись только через CLI. AINET не пишет на диск.

Skin: skin:edit разрешает только css/main.css и js/main.js (≤ 1 MB, без @import/javascript:).

AINET: только GET, scheme (http/https), port (80/443), блок private IP. Whitelist без wildcard.

6. Открытые P2 (Технический долг)
Список актуальных архитектурных компромиссов и известных недоработок.

AI-Транзакции: нет отката (Rollback) при падении одной из команд в цепочке. Батчи для слабых моделей (Qwen 7B) — ручные.

RegionContext::service(): возвращает mixed (Service Locator). Компенсируется instanceof на стороне вызывающего. Нужен @template в PHPDoc.

assert() для типов: в prod исчезает. Заменить на явные if (!instanceof) throw.

Builder неполон: нет методов table, form, section, nav, label, select, textarea.

domains_real[$domain] в save(): работает только при self-mapping (P2 — заменить на ConfigDomainResolver::real()).

PageHandler::applySkin: читает $_COOKIE напрямую, а не через LanguageNegotiator-подобный сервис.

Namespace Damoony\Algoritm: сохранён (P2 — переименовать в Damoony\Page).

AINET: только GET (POST/PUT/DELETE запрещены). Нет парсинга HTML (только JSON API и текст).

AI-оркестраторы: agent_test.py рабочий, но ai_agent/ (Planner→Builder→Content) — прототип (P3), требует перевода на multi-agent модель.

skin:bundle: не реализован (сборка CSS/JS).

Скрытые дефолты: Slot::release() возвращает пустую строку (слот без региона молча исчезает).

7. Безопасность (архитектурные решения)
RCE: удалены визуальные редакторы для страниц/блоков. RegionCompiler блокирует сырой PHP без флага allow_php_blocks=true.

Path Traversal: str_contains('..') + regex во всех CLI-командах.

SSRF: UrlGuard (схема, порт, private IP, whitelist). CURLOPT_FOLLOWLOCATION=false + повторная проверка на каждый Location.

Карантин: 6 уровней (app/, .alliasscheme/, .alliasglobal/, /content/.trash/, www/media/.trash/ и т.д.). Symlink-safe Fs::rmrf.

Content: MIME whitelist, размер ≤ 10 MB, ≥ 200×200, дедупликация по sha256. Отдача с ETag, 304, SVG-CSP.

AI Whitelist: два независимых списка — agent.config (домены для app/*) и ainet.config (URL для net:*).