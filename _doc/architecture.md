--- НАЧАЛО ДОКУМЕНТА ---

Архитектура Damoony Engine
Движок построен по принципам Explicit over implicit и Contract-first. Ниже — жизненный цикл HTTP-запроса и слои системы. Термины — по MANIFEST.md §2. Расхождения с кодом — по MANIFEST.md §6–7.

Точки входа
www/index.php — Front Controller для публичного сайта.

www/admin/index.php — Front Controller админки (обёрнут в try/catch для Safe Mode).

bin/cli.php — точка входа CLI.

Все три инициализируют контейнер (PSR-11), окружение (Env) и ядро (Kernel).

Жизненный цикл HTTP-запроса
Kernel — принимает Request, оборачивает выполнение в try/catch, логирует ошибки, возвращает Response. Гарантирует: белых экранов нет.

Middleware — стек: SecurityHeadersMiddleware (CSP, HSTS), RateLimitMiddleware (брутфорс), LanguageCookieMiddleware, SkinCookieMiddleware. Собирается в www/index.php.

FrontController:

DomainResolver::resolve(host) — определяет DomainContext. Нормализует Host (отрезает порт, trailing-dot, приводит к нижнему регистру).

Проверяет, зарегистрирован ли домен. Если нет — 404.

Проверяет доступность домена и алиаса. Если false — 503 из stubs/ (кроме /admin).

Router::match(path, domain) → RouteMatch. Проверяет флаг auth.

Создаёт RegionContext (сервисы: paths, site, auth, csrf, i18n) и передаёт в PageHandler.

Router (FileRouter) — идёт по дереву algoritm/, читает .rules.json, возвращает RouteMatch.

PageHandler — загружает RegionRegistry (через RegionLoader), подключает PHP-файл страницы, вызывает default(). Если страница возвращает null, PageHandler возвращает 204 No Content (stage-4). Если бросает исключение — в dev отдаёт сообщение, в prod — generic 500 (stage-3).

Algoritm (Страница) — возвращает Node или Response.

Response — оборачивает Node в Document (тег <html>) с мета-информацией, отдаёт HTML.

Важно (§6.1): PageHandler не оборачивает Node в Document через схему автоматически. Если страница вернула Node, он кладётся в <body> как есть — без шапки/футера/регионов. Схема применяется только если:

страница скомпилирована через PageCompiler (он вставляет SchemeLoader + SchemeRenderer в сгенерированный default());

страница наследуется от HomePage;

страница сама вызвала SchemeRenderer в default().

guide.md §4 описывает оба варианта.

Слой домена и алиаса
Домен — папка app/<real_domain>/. Входящие хосты мапятся на реальный домен через ConfigDomainResolver.

Алиас — папка внутри домена (www, doc).

Инфраструктурные папки скрыты точкой: .alliasglobal/ (блоки), .alliasscheme/ (схемы).

Резолвер: ConfigDomainResolver::resolve() нормализует хост (отрезает :8080, отрезает trailing-dot, lowercase) — stage-3. Для IP-деплоя с портом теперь работает.

Слой UI и Компоненты
UI — типизированное дерево (не строки): Node, Element, Attrs, Builder, Document, Meta. Компоненты (Form, Field, Table, Button, Message) — высокоуровневые блоки для админки. Вся вёрстка в контроллерах — через Builder там, где это возможно; сложные структуры — через new Element() или компоненты (MANIFEST.md §6.6).

Attrs::release() экранирует значения через htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE). Имена атрибутов валидируются (пропускаются только безопасные) — stage-4, XSS закрыт.

Document::release() идемпотентен — повторный вызов не дублирует <title>/<link>/<script> (stage-4).

Регионы, Слоты и Схемы
Region (RegionInterface): независимый блок контента (шапка, футер). Типы: visual (визуализация), script (скрипт), mixed (реализация). Типы в blocks.json записываются CLI, но RegionLoader их не читает (MANIFEST.md §4) — источник истины — папки.

Slot: узел в схеме для вставки региона. Slot::release() возвращает '' — если слот не заменён регионом, он молча исчезает.

Scheme: SchemeRenderer собирает каркас из манифеста и слотов.

Иерархия: локальная схема алиаса → глобальная схема домена. Если нет ни одной — в prod SchemeLoader бросает RuntimeException, в dev возвращает fallback div.layout > main[slot=center] с логом (stage-4).

Filler: переопределение региона для конкретного алиаса (<name>_<allias>.php). Управление — RegionRegistry (disable, useGlobal), приоритет: disabled → forcedGlobal → filler → base.

.data.php блоков и филлеров не читаются движком (§4). Данные — в самом PHP-классе.

Low-Code и Компиляция
Механика: админка сохраняет структуру блоков в `.editor.json` рядом с `.php` (схемы: `scheme.editor.json`, страницы: `page.editor.json`).

Схемы: `editor.json` хранит дерево блоков для UI (Drag&Drop, вложенность). При сохранении SchemeCompiler компилирует его в `data.php` (чистый PHP-массив, var_export).

Страницы: `page.editor.json` хранит статусы слотов (global/local/disabled) и контент (h1, text, code). При сохранении PageCompiler компилирует его в `algoritm_*.php`.

Legacy `.edit.json` (старый формат {blocks}) блокируется в PageController::edit() с предупреждением (stage-2a).

Компиляторы: PageCompiler (страницы), RegionCompiler (регионы) и SchemeCompiler используют var_export для строк, без addslashes и str_replace (stage-1).

UI: Редакторы схем и страниц полностью переработаны (stage-5). Работают на Drag&Drop. Разделяют зоны на «Неактивные», «Скрипты» и «Визуал». Страницы имеют вкладки (Обзор, Слоты, Настройки).

default(): null: PageHandler на null возвращает 204 No Content (stage-4). Контракт и runtime согласованы.

Слой данных
Только PDO (Connection). SQLite (тесты) / MariaDB (prod).

MigrationRunner применяет .sql из migrations/, поддерживает неявные коммиты MySQL, идемпотентен.

Прямых SQL-запросов в контроллерах — избегать, использовать $context->service('db')->pdo().

Авторизация
DbAuth (password_hash / password_verify).

Флаг auth в .rules.json проверяется FrontController → Response::redirect('/login?return=...', 302) для гостя.

В базовый класс Algoritm добавлены методы isAuthenticated() и user() (stage-5).

Админка — отдельная авторизация (config/admin.config), независимая от пользователей сайта.

Все POST — CSRF. Destructive — POST+CSRF (stage-2b).

CLI и Админка
CLI: 18 команд, возвращают Result (ok, errors), идемпотентны. structure:check — диагностика, structure:repair — восстановление.

Admin: UI-компоненты, CodeMirror (локальный), мультиязычность (RU/EN/ZH через cookie admin_lang), профиль, менеджер заглушек, дашборд безопасности, System Health. POST защищены CSRF (PRG), destructive — POST+CSRF.

Админка и CLI — равноправные способы управления, делят бизнес-логику через *NewCommand, *ToggleCommand.

Диагностика и отказоустойчивость
StructureChecker: проверяет наличие .rules.json, algoritm.php, схем, глобальных блоков, стыковку филлер↔слот, а также `.edit.json ↔ PHP` и `schemes.json ↔ файлы` (stage-5).

OverviewController (System Health): древовидный дашборд доменов и алиасов с отображением ошибок/предупреждений.

Safe Mode: www/admin/index.php перехватывает фатальные ошибки и отдаёт понятную страницу вместо белого экрана.

Безопасность
Экранирование в Attrs и Text (XSS-защита). Имена атрибутов валидируются (stage-4).

Пароли — только password_hash.

CSP из engine.config блокирует inline-скрипты (CodeMirror подключён локально из www/media/admin/).

CSRF-токены обязательны на POST. Destructive — POST+CSRF (stage-2b).

Загрузка файлов — whitelist расширений (MediaController).

Response::internalError() в PageHandler — в prod отдаёт generic сообщение (stage-3). Kernel ловит Throwable, но не Response с сообщением.

Session: HttpOnly, SameSite=Lax, Secure при HTTPS.

Что не реализовано
Headless/REST/GraphQL API, sitemap, hreflang, плагины, Schema.org, SMTP/сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента, WYSIWYG (свободная вёрстка). См. MANIFEST.md §5.

--- КОНЕЦ ДОКУМЕНТА ---