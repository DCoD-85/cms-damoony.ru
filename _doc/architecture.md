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

Middleware — стек: SecurityHeadersMiddleware (CSP, HSTS), RateLimitMiddleware (брутфорс), LanguageCookieMiddleware, SkinCookieMiddleware.

FrontController:
1. DomainResolver::resolve(host) — определяет DomainContext. Нормализует Host (отрезает порт, trailing-dot).
2. Проверяет доступность домена и алиаса. Если false — 503 из stubs/.
3. Router::match(path, domain) → RouteMatch. Проверяет флаг auth.
4. Создаёт RegionContext и передаёт в PageHandler.

Router (FileRouter) — идёт по дереву algoritm/. На каждом шаге читает правила текущего класса:
- root: algoritm/algoritm.rules.json
- уровень N: algoritm/<pathRel>/algoritm_<pathRel_underscores>.rules.json

Возвращает RouteMatch.

PageHandler — загружает RegionRegistry (через RegionLoader), подключает PHP-файл страницы, вызывает default(). Если страница возвращает null, PageHandler возвращает 204 No Content. Если бросает исключение — в dev отдаёт сообщение, в prod — generic 500.

Важно: PageHandler НЕ оборачивает Node в Document через схему автоматически. Если страница вернула Node, он кладётся в <body> как есть — без шапки/футера/регионов. Разработчик САМ обязан вызвать SchemeLoader + SchemeRenderer в коде страницы (Вариант A из guide.md), либо использовать AI-команду `page:write --with_schema=1`.

Слой домена и алиаса
Домен — папка app/<real_domain>/. Входящие хосты мапятся на реальный домен через ConfigDomainResolver.
Алиас — папка внутри домена (www, doc).
Инфраструктурные папки скрыты точкой: .alliasglobal/ (блоки), .alliasscheme/ (схемы).

realDomain: все пути в app/ строятся по realDomain из site.config (config: `domains_real[<host>] = <real>`), а не по входному хосту. Если `domains_real["damoony3.ru"] = "damoony.ru"`, то папка будет `app/damoony.ru/`. Все CLI-команды (block:new/remove, filler:new/toggle, scheme:*, structure:repair) резолвят realDomain перед построением пути.

Правило 3 файлов (Архитектурный стандарт)
Каждый логический элемент (Страница, Блок, Филлер, Схема) строго описывается тремя файлами:
1. {name}.php — PHP-код (класс или массив).
2. {name}.rules.json — Настройки (тип блока, auth, search, available, enabled).
3. {name}.editor.json — Данные для UI (деревья вложенности).

Для страниц все три файла имеют единый префикс:
- root: algoritm/algoritm.php + algoritm.rules.json + algoritm.editor.json
- /about/: algoritm/about/algoritm_about.php + algoritm_about.rules.json + algoritm_about.editor.json
- /docs/install/: algoritm/docs/install/algoritm_docs_install.php + алгоритм_docs_install.rules.json + algoritm_docs_install.editor.json

Глобальные манифесты (blocks.json, schemes.json) удалены. Источник истины — локальные файлы.

Старая модель (algoritm/.rules.json для root, {dir}/.rules.json для вложенных) мигрируется командой `migrate:page-rules`. Эта команда идемпотентна, шаги: (1) root, (2) подпапки, (3) нормализация полей, (4) создание пустых .editor.json.

Page-слой (резолвинг путей и правил)
Единый набор хелперов, используемый всеми page-командами (new/write/read/toggle):

- PagePath::resolve(Paths, domain, allias, name, parent): PagePath — возвращает targetRelPath, classChain, pageDir, phpFile, rulesFile, editorFile, а также parentDir, parentPhpFile, parentRulesFile.
- PageRules::load/save/defaults/withDefaults/withChild — единый источник .rules.json (поля available/auth/search/default).
- PageNamespace::fromSite(site, domain): string — резолв PHP-namespace из site.config (fallback: str_replace('.', '_', domain)).

Назначение: устранить дублирование формул путей/класса между 4 командами, обеспечить единый формат правил.

Слой UI и Компоненты
UI — типизированное дерево (не строки): Node, Element, Attrs, Builder, Document, Meta.
Attrs::release() экранирует значения через htmlspecialchars. Имена атрибутов валидируются (XSS закрыт).
Document::release() идемпотентен — повторный вызов не дублирует теги в <head>.

Регионы, Слоты и Схемы
Region (RegionInterface): независимый блок контента (шапка, футер). Настройки типа (visual, script, mixed) читаются из {name}.rules.json.
Slot: узел в схеме для вставки региона. Если слот не заменён регионом, он молча исчезает.
Scheme: SchemeRenderer собирает каркас из манифеста и слотов.
Filler: переопределение региона для конкретного алиаса (top_www.php). Приоритет: disabled → forcedGlobal → filler → base.

Low-Code и Компиляция (Zero-Overhead)
Механика: визуальный редактор (Drag&Drop) оставлен ТОЛЬКО для Схем. Админка сохраняет структуру схемы в scheme.editor.json, а SchemeCompiler компилирует её в {name}.php.

Страницы, Блоки и Филлеры: визуальные редакторы удалены (закрытие RCE). 

Безопасная компиляция контента (PageCompiler):
Для AI и автоматизации внедрен безопасный PageCompiler. AI передает JSON-массив блоков (h1, text, img и т.д.) через команду `page:write`. PageCompiler конвертирует этот JSON в нативный PHP-код (через Builder), используя var_export для строк. Это исключает RCE (AI не может написать сырой PHP, такой как system()).

PageCompiler::compile(namespace, classChain, blocks, withSchema = false):
- withSchema = true — генерирует код, который оборачивает контент в схему (SchemeLoader + SchemeRenderer). Используется через page:write --with_schema=1.
- withSchema = false — «голая» страница (для API, AJAX).

PageCompiler также используется в domain:new, allias:new, structure:repair для генерации stub-страницы (единый шаблон класса).

RegionCompiler: используется для компиляции блоков. Для генерации сырого PHP (custom/if) требуется включить security.allow_php_blocks=true в engine.config. По умолчанию заблокировано.

Слой данных
Только PDO (Connection). SQLite (тесты) / MariaDB (prod).
MigrationRunner применяет .sql из migrations/, идемпотентен.
db:migrate: --dir ограничен подпапками migrations/ (path traversal guard через realpath).

Авторизация
DbAuth (password_hash / password_verify).
Флаг auth в algoritm_{name}.rules.json проверяется FrontController → Response::redirect('/login?return=...', 302).
Админка — отдельная авторизация (config/admin.config). Все POST — CSRF.
admin:passwd — валидация login (3-32 [a-zA-Z0-9_.-]) и password (≥8).
user:new — валидация email, уникальность login и email, password ≥8.

CLI и Админка
CLI: 24 команды, возвращают Result (ok, errors), идемпотентны.
- Диагностика: structure:check (severity-aware), structure:repair, migrate:page-rules.
- Destructive: domain:remove, allias:remove, block:remove, scheme:remove, trash:clear — все в карантин (`*_trash_<timestamp>/`), требуют --force.
- Карантин: 4 уровня (app/, app/<domain>/, .alliasscheme/, .alliasglobal/). Очистка — trash:clear с флагами --force/--older/--domain.
- Path traversal guard: все команды с --domain/--name проверяют `..` и regex.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.

Admin: Тонкий клиент. Контроллеры занимаются только рендерингом HTML и валидацией форм. Вся файловая логика делегируется вызовом CLI-команд, включая scheme:copy/remove/save (было: прямое в SchemeController). CodeMirror для страниц/блоков. Визуальный Drag&Drop только для Схем. POST защищены CSRF.
Админка и CLI делят бизнес-логику.

Диагностика
StructureChecker проверяет:
- наличие всех 3 файлов для каждой страницы (алгоритм_{name}.{php,rules.json,editor.json});
- наличие алгоритм/algoritm.rules.json (root);
- устаревшие {dir}/.rules.json (warning → migrate:page-rules);
- стыковку филлер↔слот;
- наличие схем и их .rules.json;
- наличие .php/.rules.json/.editor.json для блоков.

Severity:
- error: отсутствует .php, отсутствует алгоритм/algoritm.rules.json, отсутствует схема.
- warning: отсутствует .editor.json, отсутствует .rules.json у блока/схемы, устаревший .rules.json, филлер без слота.

StructureCheckCommand: `$r->ok = true`, если нет errors. Warnings отображаются через `⚠` и не валят команду (exit 0). При errors — `✗` и exit 1.

StructureRepairCommand: восстанавливает отсутствующие root-страницы (algoritm.php через PageCompiler, algoritm.rules.json, algoritm.editor.json), algoritm.rules.json для схем, {name}.rules.json/.editor.json для блоков. Path traversal guard на domain.

AI-Слой (Agent Integration)
Архитектура интеграции LLM (например, локальной Ollama через Qwen 2.5 Coder):
AI (Python/LLM) -> bin/aicli.php -> bin/cli.php -> Файловая система

1. AI генерирует JSON-план (массив команд и блоков).
2. Python-скрипт очищает JSON от синтаксических ошибок (висячие запятые) и передает в bin/aicli.php через STDIN.
3. aicli.php проверяет команду по Whitelist (разрешены безопасные операции: domain:new, page:write и т.д.).
4. aicli.php вызывает bin/cli.php с жестко заданными аргументами (JSON кодируется в base64).
5. Результат (JSON) возвращается агенту для анализа.

Безопасность AI-слоя:
- Агент не имеет доступа к shell (exec/shell_exec).
- Жесткая валидация аргументов (regex), включая domain (по whitelist из agent.config), allias/name ([a-z][a-z0-9_]*).
- Whitelist команд (запрещены user:new, db:migrate, admin:passwd, domain:remove, allias:remove, block:remove, trash:clear, migrate:page-rules, scheme:*).
- Изоляция доменов (агент может создавать только домены из config/agent.config).
- Спец. ключ `__root__` используется для записи контента на главную страницу аллиаса (/, так как CLI не принимает пустой аргумент --name). Внутри PageWriteCommand конвертируется в пустой name. `--parent` с `__root__` несовместим (ошибка).

Диагностика и отказоустойчивость
StructureChecker: severity-aware (error/warning), см. раздел «Диагностика».
OverviewController (System Health): древовидный дашборд доменов и алиасов.
Safe Mode: www/admin/index.php перехватывает фатальные ошибки и отдаёт понятную страницу.

Безопасность
Экранирование в Attrs и Text (XSS-защита).
Пароли — только password_hash. Минимум 8 символов (admin:passwd, user:new).
CSP из engine.config блокирует inline-скрипты.
CSRF-токены обязательны на POST. Destructive — POST+CSRF (UI) или --force (CLI).
Session: HttpOnly, SameSite=Lax, Secure при HTTPS.
Path traversal guard: все CLI-команды с --domain/--name проверяют `..` и regex. db:migrate --dir ограничен migrations/.
Карантин: destructive CLI-операции не удаляют сразу, а перемещают в `*_trash_<timestamp>/`.

Качество
PHPStan level 8: 0 errors, 204 файла (src/, bin/, www/).
PHP-CS-Fixer: 0 issues, 204 файла (src/, bin/, www/, tests/).
PHPUnit: 336 тестов, 798 assertions, 1 skipped.
CI: phpunit + phpstan + cs-fixer --dry-run.

Что не реализовано
Headless/REST API для внешних систем (кроме локального aicli), sitemap, hreflang, плагины, SMTP/сброс пароля, Redis/Memcached/CDN, мультиязычность контента, WYSIWYG. См. MANIFEST.md §5.

--- КОНЕЦ ДОКУМЕНТА ---