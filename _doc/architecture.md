--- НАЧАЛО ДОКУМЕНТА ---

Архитектура Damoony Engine
Движок построен по принципам Explicit over implicit, Contract-first и CLI-First. Ниже — жизненный цикл HTTP-запроса, слои системы и принципы интеграции с AI. Термины — по MANIFEST.md §2. Расхождения с кодом — по MANIFEST.md §6–7.

Точки входа
www/index.php — Front Controller для публичного сайта.
www/admin/index.php — Front Controller админки (обёрнут в try/catch для Safe Mode).
bin/cli.php — точка входа CLI (Единый источник истины для файловых операций).
bin/aicli.php — безопасный фасад (API) для интеграции с AI-агентами.

Все точки входа инициализируют контейнер (PSR-11), окружение (Env) и ядро (Kernel).

Жизненный цикл HTTP-запроса
Kernel — принимает Request, оборачивает выполнение в try/catch, логирует ошибки, возвращает Response. Гарантирует: белых экранов нет.

Middleware — стек: SecurityHeadersMiddleware (CSP, HSTS), RateLimitMiddleware (брутфорс), LanguageCookieMiddleware, SkinCookieMiddleware. Собирается в www/index.php.

FrontController:
1. DomainResolver::resolve(host) — определяет DomainContext. Нормализует Host (отрезает порт, trailing-dot, lowercase).
2. Проверяет доступность домена и алиаса. Если false — 503 из stubs/.
3. Router::match(path, domain) → RouteMatch. Проверяет флаг auth.
4. Создаёт RegionContext и передаёт в PageHandler.

Router (FileRouter) — идёт по дереву algoritm/, читает .rules.json, возвращает RouteMatch.

PageHandler — загружает RegionRegistry (через RegionLoader), подключает PHP-файл страницы, вызывает default(). Если страница возвращает null, PageHandler возвращает 204 No Content. Если бросает исключение — в dev отдаёт сообщение, в prod — generic 500.

Важно: PageHandler НЕ оборачивает Node в Document через схему автоматически. Если страница вернула Node, он кладётся в <body> как есть — без шапки/футера/регионов.
Так как визуальный редактор страниц удален (ради безопасности), разработчик САМ обязан вызвать SchemeLoader + SchemeRenderer в коде страницы (Вариант A из guide.md), если ему нужен каркас. Скрытой магии больше нет.

Слой домена и алиаса
Домен — папка app/<real_domain>/. Входящие хосты мапятся на реальный домен через ConfigDomainResolver.
Алиас — папка внутри домена (www, doc).
Инфраструктурные папки скрыты точкой: .alliasglobal/ (блоки), .alliasscheme/ (схемы).

Правило 3 файлов (Архитектурный стандарт)
Каждый логический элемент системы (Страница, Блок, Филлер, Схема) строго описывается тремя файлами:
1. {name}.php — PHP-код (класс или массив).
2. {name}.rules.json — Настройки (тип блока, auth, search, enabled).
3. {name}.editor.json — Данные для UI (деревья вложенности для визуального редактора схем).
Глобальные манифесты (blocks.json, schemes.json) удалены. Источник истины — локальные файлы.

Слой UI и Компоненты
UI — типизированное дерево (не строки): Node, Element, Attrs, Builder, Document, Meta.
Attrs::release() экранирует значения через htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE). Имена атрибутов валидируются (XSS закрыт).
Document::release() идемпотентен — повторный вызов не дублирует <title>/<link>/<script>.

Регионы, Слоты и Схемы
Region (RegionInterface): независимый блок контента (шапка, футер). Настройки типа (visual, script, mixed) читаются из {name}.rules.json.
Slot: узел в схеме для вставки региона. Slot::release() возвращает '' — если слот не заменён регионом, он молча исчезает.
Scheme: SchemeRenderer собирает каркас из манифеста и слотов.
Иерархия схем: локальная схема алиаса (www.php) → глобальная схема домена. В prod SchemeLoader бросает RuntimeException, если схемы нет.
Filler: переопределение региона для конкретного алиаса (top_www.php). Управление — RegionRegistry (disable, useGlobal). Приоритет: disabled → forcedGlobal → filler → base.

Low-Code и Компиляция
Механика: визуальный редактор (Drag&Drop) оставлен ТОЛЬКО для Схем. Админка сохраняет структуру схемы в scheme.editor.json.
Схемы: editor.json хранит дерево блоков для UI. При сохранении SchemeCompiler компилирует его в {name}.php (чистый PHP-массив, var_export).
Страницы, Блоки и Филлеры: визуальные редакторы удалены (закрытие RCE). Редактирование происходит напрямую в PHP-файлах через CodeMirror.
RegionCompiler: используется для компиляции блоков. Для генерации сырого PHP (блоки custom/if/else/endif) требуется включить security.allow_php_blocks=true в engine.config. По умолчанию заблокировано.

Слой данных
Только PDO (Connection). SQLite (тесты) / MariaDB (prod).
MigrationRunner применяет .sql из migrations/, идемпотентен.
Прямых SQL-запросов в контроллерах — избегать.

Авторизация
DbAuth (password_hash / password_verify).
Флаг auth в .rules.json проверяется FrontController → Response::redirect('/login?return=...', 302).
Админка — отдельная авторизация (config/admin.config).
Все POST — CSRF. Destructive — POST+CSRF.

CLI и Админка
CLI: 22 команды, возвращают Result (ok, errors), идемпотентны. structure:check — диагностика, structure:repair — восстановление (создает .rules.json и .editor.json).
Admin: Тонкий клиент. Контроллеры занимаются только рендерингом HTML и валидацией форм. Вся файловая логика делегируется вызовом CLI-команд (например, SchemeRemoveCommand, SchemeSaveCommand).
Админка и CLI делят бизнес-логику.

AI-Слой (Agent Integration)
Архитектура интеграции LLM (например, локальной Ollama через Gemma 4):
AI (Python/LLM) -> bin/aicli.php -> bin/cli.php -> Файловая система

1. AI генерирует запрос к инструменту (JSON).
2. Python-скрипт передает JSON в bin/aicli.php через STDIN.
3. aicli.php проверяет команду по Whitelist (разрешены только безопасные операции).
4. aicli.php вызывает bin/cli.php с жестко заданными аргументами.
5. Результат (JSON) возвращается агенту для анализа.

Безопасность AI-слоя:
- Агент не имеет доступа к shell (exec/shell_exec).
- Жесткая валидация аргументов (regex).
- Whitelist команд (например, разрешен filler:toggle, но запрещен user:new или db:migrate).
- Изоляция доменов (агент может создавать только test-* домены).

Диагностика и отказоустойчивость
StructureChecker: проверяет наличие всех 3 файлов для каждого элемента (Страницы, Блоки, Схемы). Проверяет стыковку филлер↔слот.
OverviewController (System Health): древовидный дашборд доменов и алиасов.
Safe Mode: www/admin/index.php перехватывает фатальные ошибки и отдаёт понятную страницу.

Безопасность
Экранирование в Attrs и Text (XSS-защита).
Пароли — только password_hash.
CSP из engine.config блокирует inline-скрипты.
CSRF-токены обязательны на POST. Destructive — POST+CSRF.
Response::internalError() в PageHandler — в prod отдаёт generic сообщение.
Session: HttpOnly, SameSite=Lax, Secure при HTTPS.

Что не реализовано
Headless/REST API для внешних систем (кроме локального aicli), sitemap, hreflang, плагины, SMTP/сброс пароля, Redis/Memcached/CDN, мультиязычность контента, WYSIWYG. См. MANIFEST.md §5.

--- КОНЕЦ ДОКУМЕНТА ---