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

DomainResolver::resolve(host) — определяет DomainContext.

Проверяет, зарегистрирован ли домен. Если нет — 404.

Проверяет доступность домена и алиаса. Если false — 503 из stubs/ (кроме /admin).

Router::match(path, domain) → RouteMatch. Проверяет флаг auth.

Создаёт RegionContext (сервисы: paths, site, auth, csrf, i18n) и передаёт в PageHandler.

Router (FileRouter) — идёт по дереву algoritm/, читает .rules.json, возвращает RouteMatch.

PageHandler — загружает RegionRegistry (через RegionLoader), подключает PHP-файл страницы, вызывает default().

Algoritm (Страница) — возвращает Node или Response.

Response — оборачивает Node в Document (тег <html>) с мета-информацией, отдаёт HTML.

Важно (§6.1): PageHandler не оборачивает Node в Document через схему автоматически. Если страница вернула Node, он кладётся в <body> как есть — без шапки/футера/регионов. Схема применяется только если:

страница скомпилирована через PageCompiler (он вставляет SchemeLoader + SchemeRenderer в сгенерированный default());

страница наследуется от HomePage (демо-класс, делает то же);

страница сама вызвала SchemeRenderer в default().

Это известный дефект API (MANIFEST.md §6.1, P1). Не «скрытый дефолт» — скрытая разница между ручной и визуальной страницей. guide.md §4 описывает оба варианта.

Слой домена и алиаса
Домен — папка app/<real_domain>/. Входящие хосты мапятся на реальный домен через ConfigDomainResolver.

Алиас — папка внутри домена (www, doc).

Инфраструктурные папки скрыты точкой: .alliasglobal/ (блоки), .alliasscheme/ (схемы).

Резолвер: ConfigDomainResolver::resolve() приводит хост к нижнему регистру, отрезает порт (:8080) и трейлинг-точку (example.ru.) — нормализация добавлена в stage-3. Для localhost без порта — работает.

Слой UI и Компоненты
UI — типизированное дерево (не строки): Node, Element, Attrs, Builder, Document, Meta. Компоненты (Form, Field, Table, Button, Message) — высокоуровневые блоки для админки. Вся вёрстка в контроллерах — через Builder там, где это возможно; сложные структуры — через new Element() или компоненты (MANIFEST.md §6.6).

Attrs::release() экранирует значения через htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE). Имена атрибутов не валидируются (§7.2 п.2, P1) — не передавайте в attrs()->add() имена из пользовательских данных.

Регионы, Слоты и Схемы
Region (RegionInterface): независимый блок контента (шапка, футер). Типы: ui (визуализация), logic (скрипт), mixed (реализация). Типы в blocks.json записываются CLI, но RegionLoader их не читает (MANIFEST.md §4) — источник истины — папки.

Slot: узел в схеме для вставки региона. Slot::release() возвращает '' — если слот не заменён регионом, он молча исчезает.

Scheme: SchemeRenderer собирает каркас из манифеста и слотов.

Иерархия: локальная схема алиаса → глобальная схема домена. Инвариант — «если нет ни одной, 500». Фактически SchemeLoader всегда возвращает fallback div.layout > main[slot=center] (§6.5, P1). Скрытых дефолтов должно быть нет — сейчас есть, это баг.

Filler: переопределение региона для конкретного алиаса (<name>_<allias>.php). Управление — RegionRegistry (disable, useGlobal), приоритет: disabled → forcedGlobal → filler → base.

.data.php блоков и филлеров не читаются движком (§4). Данные — в самом PHP-классе.

Low-Code и Компиляция
Механика: админка сохраняет структуру блоков в .edit.json рядом с .php.

Два формата .edit.json: {blocks} (старый) и {slots} (новый). Legacy {blocks} блокируется в PageController::edit()/save() с предупреждением (stage-2a). Автомиграция не реализована, но регрессия закрыта.

Компиляция: PageCompiler (страницы), RegionCompiler (регионы) читают JSON и генерируют PHP с вызовами Builder и var_export для строк. SchemeCompiler — просто var_export, str_replace удалён (stage-1, §7.1 п.5).

~~addslashes в PageCompiler/RegionCompiler~~ — ✅ закрыто (stage-1, var_export).

UI: VisualEditorRenderer (в Admin/Support/) генерирует формы. Визуальный редактор схем сохраняет плоский список слотов — вложенные схемы схлопываются (§6.15, P1).

default(): null: интерфейс разрешает null, но PageHandler на null бросает RuntimeException → 500 (§6.18, P1).

Слой данных
Только PDO (Connection). SQLite (тесты) / MariaDB (prod).

MigrationRunner применяет .sql из migrations/, поддерживает неявные коммиты MySQL, идемпотентен.

Прямых SQL-запросов в контроллерах — избегать, использовать $context->service('db')->pdo().

Авторизация
DbAuth (password_hash / password_verify).

Флаг auth в .rules.json проверяется FrontController → Response::redirect('/login?return=...', 302) для гостя.

Админка — отдельная авторизация (config/admin.config), независимая от пользователей сайта.

Все POST — CSRF. Destructive — POST+CSRF (stage-2b).

CLI и Админка
CLI: 17 команд, возвращают Result (ok, errors), идемпотентны. structure:check — диагностика.

Admin: UI-компоненты, CodeMirror (локальный), мультиязычность (RU/EN/ZH через cookie admin_lang), профиль, менеджер заглушек, дашборд безопасности, System Health. POST защищены CSRF (PRG), destructive — POST+CSRF.

Админка и CLI — равноправные способы управления, делят бизнес-логику через *NewCommand, *ToggleCommand.

Диагностика и отказоустойчивость
StructureChecker: проверяет наличие .rules.json, algoritm.php, схем, глобальных блоков, стыковку филлер↔слот. Не проверяет: .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки, schemes.json ↔ файлы (это ловит «сироту» visuo.data.php без записи в schemes.json). §7.2 п.3, P1.

OverviewController (System Health): древовидный дашборд доменов и алиасов с отображением ошибок/предупреждений.

Safe Mode: www/admin/index.php перехватывает фатальные ошибки и отдаёт понятную страницу вместо белого экрана.

Безопасность
Экранирование в Attrs и Text (XSS-защита). Исключение: имена атрибутов не валидируются (§7.2 п.2, P1).

Пароли — только password_hash.

CSP из engine.config блокирует inline-скрипты (CodeMirror подключён локально из www/media/admin/).

CSRF-токены обязательны на POST. Destructive — POST+CSRF (stage-2b).

Загрузка файлов — whitelist расширений (MediaController).

Response::internalError() в PageHandler — в prod отдаёт generic сообщение (stage-3, §7.1 п.3). Kernel ловит Throwable, но не Response с сообщением.

Session: HttpOnly, SameSite=Lax, Secure при HTTPS.

Что не реализовано
Headless/REST/GraphQL API, sitemap, hreflang, плагины, Schema.org, SMTP/сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента, WYSIWYG (свободная вёрстка). См. MANIFEST.md §5.

--- КОНЕЦ ДОКУМЕНТА ---