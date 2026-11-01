--- НАЧАЛО ДОКУМЕНТА ---

Архитектура Damoony Engine
Движок построен по принципам Explicit over implicit, Contract-first и CLI-First. Ниже — жизненный цикл HTTP-запроса, слои системы и принципы интеграции с AI. Термины — по MANIFEST.md §2. Расхождения с кодом — по MANIFEST.md §6–7.

Точки входа
www/index.php — Front Controller для публичного сайта.
www/admin/index.php — Front Controller админки (обёрнут в try/catch для Safe Mode).
bin/cli.php — точка входа CLI (Единый источник истины для файловых операций).
bin/aicli.php — единственный безопасный фасад (API) для AI-агентов.
bin/ainet.php — standalone-отладка AINET (не для AI).

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
- /docs/install/: algoritm/docs/install/algoritm_docs_install.php + algoritm_docs_install.rules.json + algoritm_docs_install.editor.json

Глобальные манифесты (blocks.json, schemes.json) удалены. Источник истины — локальные файлы.

Старая модель (algoritm/.rules.json для root, {dir}/.rules.json для вложенных) мигрируется командой `migrate:page-rules`. Эта команда идемпотентна, шаги: (1) root, (2) подпапки, (3) нормализация полей, (4) создание пустых .editor.json.

Page-слой (резолвинг путей и правил)
Единый набор хелперов, используемый всеми page-командами (new/write/read/toggle):

- PagePath::resolve(Paths, domain, allias, name, parent): PagePath — возвращает targetRelPath, classChain, pageDir, phpFile, rulesFile, editorFile, а также parentDir, parentPhpFile, parentRulesFile.
- PageRules::load/save/defaults/withDefaults/withChild — единый источник .rules.json (поля available/auth/search/default).
- PageNamespace::fromSite(site, domain): string — резолв PHP-namespace из site.config (fallback: str_replace('.', '_', domain)).

Назначение: устранить дублирование формул путей/класса между 4 командами, обеспечить единый формат правил.

Слой медиа (www/media/)
Зеркалит app/<domain>/<allias>/:
```
app/example.ru/www/algoritm/about/...  →  www/media/example.ru/www/about/...
```

Три зоны (не смешиваются):
```
www/media/
├── admin/                          # инфраструктура (git, руками)
├── shared/                         # инфраструктура (git, руками)
├── .trash/                         # карантин медиа (runtime, .gitignore)
├── <domain>/skin/                  # скины (git, руками)
└── <domain>/<allias>/<category>/   # AI-контент (runtime, .gitignore)
    ├── <hash>.jpg
    └── <hash>.meta.json
```

Файл: `<hash>.<ext>` (sha256 содержимого, первые 16 символов) + `<hash>.meta.json` (source_url, alt, size, mime, width, height, downloaded_at).
Дедупликация по hash: повторная запись того же содержимого возвращает существующий путь.
Запись — **только** через `cli.php media:store` (единственный источник истины для FS). AINET не пишет на диск.

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
Механика: визуальный редактор (Drag&Drop) оставлен ТОЛЬКО для Схем. Админка сохраняет структуру схемы в scheme.editor.json, а SchemeCompiler компилирует её в {name}.php через CLI `scheme:save`.

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
CLI: 27 команд, возвращают Result (ok, errors), идемпотентны.
- app/ (24): domain:*, allias:*, block:*, filler:*, page:*, scheme:*, db:migrate, admin:passwd, user:new, trash:clear, structure:check, structure:repair, migrate:page-rules, help.
- media/ (3): media:store, media:remove, media:list.
- Диагностика: structure:check (severity-aware), structure:repair, migrate:page-rules.
- Destructive: domain:remove, allias:remove, block:remove, scheme:remove, media:remove, trash:clear — все в карантин (`*_trash_<timestamp>/`), требуют --force.
- Карантин: 5 уровней (app/, app/<domain>/, .alliasscheme/, .alliasglobal/, www/media/.trash/). Очистка — trash:clear с флагами --force/--older/--domain. Symlink-safe Fs::rmrf.
- Path traversal guard: все команды с --domain/--name проверяют `..` и regex.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- Media: дедупликация по hash (media:store).

Admin: Тонкий клиент. Контроллеры занимаются только рендерингом HTML и валидацией форм. Вся файловая логика делегируется вызовом CLI-команд, включая scheme:copy/remove/save и media:*. CodeMirror для страниц/блоков. Визуальный Drag&Drop только для Схем. POST защищены CSRF.
Админка и CLI делят бизнес-логику.

Диагностика
StructureChecker проверяет:
- наличие всех 3 файлов для каждой страницы (algoritm_{name}.{php,rules.json,editor.json});
- наличие algoritm/algoritm.rules.json (root);
- устаревшие {dir}/.rules.json (warning → migrate:page-rules);
- стыковку филлер↔слот;
- наличие схем и их .rules.json;
- наличие .php/.rules.json/.editor.json для блоков.

Severity:
- error: отсутствует .php, отсутствует algoritm/algoritm.rules.json, отсутствует схема.
- warning: отсутствует .editor.json, отсутствует .rules.json у блока/схемы, устаревший .rules.json, филлер без слота.

StructureCheckCommand: `$r->ok = true`, если нет errors. Warnings отображаются через `⚠` и не валят команду (exit 0). При errors — `✗` и exit 1.

StructureRepairCommand: восстанавливает отсутствующие root-страницы (algoritm.php через PageCompiler, algoritm.rules.json, algoritm.editor.json), algoritm.rules.json для схем, {name}.rules.json/.editor.json для блоков. Path traversal guard на domain.

AI-Слой (Agent Integration)
Единственная точка входа для AI — `bin/aicli.php`. AINET — библиотека, не отдельный фасад.

Архитектура:
```
[AI-Модель (Ollama, Qwen)]
      ↕ (JSON-план)
[Python Скрипт (Agentic Loop)]
      ↕ (JSON через STDIN)
[bin/aicli.php (Единственный AI-фасад)]
      │
      ├── app/* команды (page:*, domain:*, allias:*, filler:*)
      │       └── shell_exec → bin/cli.php → FS (app/)
      │
      ├── media:* (только внутренний вызов из image:download)
      │       └── shell_exec → bin/cli.php → FS (www/media/)
      │
      └── net:* (net:status, fetch, image:search, image:download)
              ├── fetch, image:search, net:status → src/Ainet/* (in-process) → Интернет
              └── image:download → AinetFacade (bytes) → shell_exec → cli.php media:store → FS
```

Разделение ответственности:
- **bin/aicli.php** — единственная точка входа для AI. Whitelist, валидация, диспетчеризация. Не содержит бизнес-логику AINET/CLI. Не пишет на диск.
- **src/Ainet/** — сеть: SSRF (UrlGuard), whitelist (DomainWhitelist), HTTP (HttpClient, CurlTransport), кэш (Cache), провайдеры (ImageSearch), публичный API (AinetFacade). **Не пишет на диск.**
- **bin/cli.php** — FS: единственный источник истины для записи/удаления/списка. Все media:* операции.
- **bin/ainet.php** — standalone-отладка (не в AI-whitelist).

Безопасность AI-слоя:
- Агент не имеет доступа к shell напрямую. Все вызовы — через whitelist.
- Жесткая валидация аргументов (regex): domain, allias, name, block, category.
- Whitelist команд (12): `read_test_file`, `engine_status`, `filler_toggle`, `domain:new`, `allias:new`, `page:new`, `page:write`, `page:read`, `net:status`, `image:search`, `image:download`, `fetch`.
- Запрещены: `user:new`, `db:migrate`, `admin:passwd`, destructive (`*:remove`), `scheme:*`, `block:*`, `filler:new`, `trash:clear`, `migrate:page-rules`, `structure:*`, `media:*` (кроме внутреннего вызова).
- Два whitelist доменов: `config/agent.config` (allowed_domains для app/* и image:download.domain) + `config/ainet.config` (allowed_domains для URL net:*).
- Изоляция доменов: AI может создавать только домены из `agent.config`.
- Спец. ключ `__root__` — для записи на главную страницу аллиаса. `--parent` с `__root__` — ошибка.

AINET (SSRF-защита, no RCE):
- UrlGuard: scheme ∈ {http, https}, port ∈ {80, 443}, host в whitelist, private IP block (127/8, 10/8, 172.16/12, 192.168/16, 169.254/16, ::1, fc00::/7, fe80::/10), no user:pass, query ≤ 2048.
- HttpClient: GET-only, ручные редиректы (CURLOPT_FOLLOWLOCATION=false) → повторный UrlGuard на каждый Location.
- Cache: sha256(url) → file+meta, TTL 7 дней.
- Rate limit: 30 req/min.
- Провайдеры: wikimedia (default, без ключа), openverse (fallback, без ключа). Автофоллбэк primary → next (только для default provider).

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
Карантин: destructive CLI-операции не удаляют сразу, а перемещают в `*_trash_<timestamp>/` (5 уровней).
SSRF-защита: UrlGuard в AINET, whitelist доменов, private IP block, ручные редиректы.

Качество
PHPStan level 8: 0 errors, 240 файлов (src/, bin/, www/).
PHP-CS-Fixer: 0 issues, 240 файлов (src/, bin/, www/, tests/).
PHPUnit: 472 теста, 1157 assertions, 1 skipped.
CI: phpunit + phpstan + cs-fixer --dry-run.

Что не реализовано
Headless/REST API для внешних систем (кроме локального aicli), sitemap, hreflang, плагины, SMTP/сброс пароля, Redis/Memcached/CDN, мультиязычность контента, WYSIWYG, POST-запросы в AINET, парсинг HTML. См. MANIFEST.md §5.

--- КОНЕЦ ДОКУМЕНТА ---