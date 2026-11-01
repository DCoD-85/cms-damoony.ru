--- НАЧАЛО ДОКУМЕНТА ---

Контракты проекта Damoony
Документ фиксирует текущие архитектурные контракты (интерфейсы и границы ответственности). Код реализаций скрыт за этими контрактами. Термины — по MANIFEST.md §2. Известные расхождения контракта и поведения — по MANIFEST.md §6–7.

Принцип: если интерфейс разрешает что-то, а runtime это ломает — это баг контракта, а не «особенность».

DomainResolver (Разрешение доменов)
Назначение: определение реального домена и алиаса по входящему хосту.

Методы:
resolve(string $host): DomainContext
exists(string $domain): bool
real(string $domain): string
namespace(string $domain): string

DomainContext (readonly):
domain: string — входной хост (как пришёл)
real: string — канонический домен (папка в app/)
allias: string — текущий алиас (ключ из alliases_real)
skin: string
language: string
available: bool — доступен ли домен И алиас
stub: ?string
namespace: string — PHP-namespace
scheme: string — имя схемы (используется в SchemeLoader)

Инварианты:
Резолвер не бросает исключений → DomainContext с available=false.
Отвечает только за вычисление контекста. Не загружает контроллеры и не рендерит страницы.
Host нормализуется (порт, trailing-dot отрезаются).
real — источник истины для путей в app/. Все CLI-команды, которые пишут в app/, строят путь через realDomain, а не через входной хост.

Ограничения (P2):
domains_real[$domain] работает только при self-mapping ("example.ru": "example.ru"). Без self-mapping → Undefined array key.
DomainContext->allias возвращает ключ из конфига, а папка на диске может называться иначе (если mapping не identity). Требует решения в P2.

Источник: src/Domain/

Router (Маршрутизация)
Назначение: поиск класса контроллера по URL и контексту домена.

Методы:
match(string $path, DomainContext $domain): RouteMatch

RouteMatch (readonly):
found: bool
classChain: ?string (например, algoritm_about)
pathRel: ?string
args: array (параметры из regex)
auth: bool
code: int

Инварианты:
Роутер не выполняет код контроллера.
Роутер не рендерит ошибки → RouteMatch с found=false и code.
Правила — в algoritm/algoritm.rules.json (root) и algoritm_{name}.rules.json рядом со страницами.
На каждом шаге роутинга FileRouter читает правила текущего класса (root → промежуточные → leaf).

Ограничения:
Regex-параметры формата regex|target; @preg_match глушит ошибки — битый regex даст тихий notFound, не диагностику.

Источник: src/Routing/

Region (Регионы и Блоки)
Назначение: единица шаблона (шапка, подвал, сайдбар), вставляемая в слоты схемы.

Методы (RegionInterface):
name(): string
enabled(): bool
render(RegionContext $context): Node

RegionRegistry:
add(RegionInterface $region, bool $isBase = false)
disable(string $name) / enable(string $name)
useGlobal(string $name)
useFiller(string $name)
get(string $name): ?RegionInterface — приоритет: disabled → forcedGlobal → filler → base

Инварианты:
Регион не знает о других регионах и о схеме. Возвращает Node, не строку.
Настройки (тип блока: script, visual, mixed) хранятся в {name}.rules.json.
Источник истины — папки .alliasglobal/*/, а не глобальные манифесты.

Ограничения:
RegionContext::service(string): mixed — Service Locator. @template в PHPDoc отсутствует (§6.2) — компенсация через instanceof на стороне вызывающего.
RegionLoader молча пропускает битые блоки (класс не реализует RegionInterface) — диагностика через structure:check.

Источник: src/Region/

UI (Дерево представления и Компоненты)
Назначение: типизированное дерево DOM, не строки.

Базовые элементы: Node (интерфейс), Element (HTML-тег), Attrs (атрибуты), Builder (хелперы, 12 методов), Document (html/head/body), Meta (title/CSS/JS), Text, Fragment, Group.

Компоненты: Form, Field, Table, Button, Message.

Инварианты:
Дерево мутабельно: append() / prepend() / content() меняют узел in-place. release() возвращает HTML-строку.
Экранирование данных — на уровне Attrs и Text (htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)).
Имена атрибутов в Attrs::release() валидируются (XSS закрыт).
Document::release() идемпотентен — повторный вызов не дублирует <title>/<link>/<script>.

Ограничения:
Node — контракт с изъяном: Text::append(), Slot::append() бросают LogicException.
Slot::release() возвращает '' — слот без региона молча исчезает.
Builder неполон (нет table, form, section, nav, label, select, textarea) — сложные структуры через new Element() или компоненты.

Источник: src/Ui/

Scheme (Схемы)
Назначение: HTML-каркас страницы, собираемый из регионов через слоты.

Методы:
SchemeLoader::load(DomainContext $domain, string $schemeName = 'www'): array
SchemeRenderer::render(array $manifest, RegionRegistry $regions, RegionContext $context): Element

Правило 3 файлов для схем:
- {name}.php — Массив для рендеринга (var_export).
- {name}.rules.json — Настройки (enabled).
- {name}.editor.json — Дерево для UI (Drag&Drop).
Глобальный манифест schemes.json удален.

Инварианты:
Схема — декларативный массив без бизнес-логики.
Схема не знает о содержимом регионов.
Иерархия: локальная схема алиаса → глобальная схема домена → 500. Скрытых дефолтов нет.

Ограничения:
SchemeCompiler::compile() использует var_export (без str_replace).
Визуальный редактор (Drag&Drop) сохраняет вложенность и обновляет editor.json, а затем компилирует в php через CLI scheme:save.

Источник: src/Scheme/

Page (Страницы и Page-слой)
Назначение: единое представление страницы и её файлов (php, rules.json, editor.json).

Базовый класс (src/Algoritm/Page.php):
Page implements PageInterface
- __construct(RegionContext, RegionRegistry, array $args)
- default(): Node|Response|null
- isAjax(): bool
- isPost(): bool
- isAuthenticated(): bool
- user(): mixed
- regions(): RegionRegistry
- setContent(string $slotName, Node $content): self

PageInterface (src/Algoritm/PageInterface.php):
- __construct(RegionContext, RegionRegistry, array $args)
- default(): Node|Response|null
- isAjax(): bool
- isPost(): bool

Page-слой (src/Page/):
PagePath::resolve(Paths, domain, allias, name, parent): PagePath
  Возвращает: targetRelPath, classChain, pageDir, phpFile, rulesFile, editorFile,
  parentRelPath, parentClassChain, parentDir, parentPhpFile, parentRulesFile.

PageRules::load(string $file): array
PageRules::save(string $file, array $rules): void
PageRules::defaults(): array{available: list<string>, auth: bool, search: bool, default: bool}
PageRules::withDefaults(array $current): array{...}
PageRules::withChild(array $rules, string $childName): array

PageNamespace::fromSite(array $site, string $domain): string

PageCompiler::compile(namespace, classChain, blocks, withSchema = false): string
  Компилирует JSON-блоки в PHP-код страницы. withSchema=true → оборачивает в схему.
  Используется в page:write, а также для stub-страниц в domain:new/allias:new/structure:repair.

Инварианты:
- Один класс = algoritm_{name}.{php,rules.json,editor.json} в одной директории.
- Root: algoritm/algoritm.{php,rules.json,editor.json}.
- Все 4 page-команды (new/write/read/toggle) используют PagePath и PageRules — без дублирования формул.
- __root__ в CLI = пустой name внутри (главная страница). --parent с __root__ — ошибка.
- page:new создаёт stub через PageCompiler (пустой Builder::div('page-content')).

Ограничения:
- Namespace `Damoony\Algoritm` сохранён (P2: переименовать в `Damoony\Page`).

Источник: src/Algoritm/, src/Page/

Auth (Авторизация и Безопасность)
Назначение: аутентификация и защита от подделки запросов.

Методы:
AuthInterface: login(), logout(), isAuthenticated(), user()
DbAuth (база данных)
Csrf: field(), check()
Page (Базовый класс): isAuthenticated(): bool, user(): mixed

Инварианты:
Пароли — только password_hash. Минимум 8 символов (admin:passwd, user:new).
Все POST защищены CSRF. Без исключений.
Флаг auth: true в algoritm_{name}.rules.json автоматически перенаправляет гостя на /login.
Все destructive требуют POST+CSRF (UI) или --force (CLI). UI переведён на POST-формы.
Админка использует отдельную авторизацию (config/admin.config), независимую от пользователей сайта.
admin:passwd — валидация login (3-32 [a-zA-Z0-9_.-]) и password (≥8).
user:new — валидация email (FILTER_VALIDATE_EMAIL), уникальность login и email, password ≥8.

Источник: src/Auth/, src/Security/, src/Admin/Auth.php

I18n (Мультиязычность)
Назначение: перевод интерфейса админки и сайта.
Классы: Translator (t(key, params)), LanguageNegotiator (URL → Cookie → Конфиг).
Хранилище: файлы lang/{code}.php.
Ограничения: Фронтенд-переключатель и hreflang не реализованы. Translator создается вручную в FrontController (P2).

Skins (Скины)
Назначение: управление визуальными темами.
Классы: SkinManager.
Инварианты: PageHandler добавляет main.css/main.js активного скина в Meta.
Расхождение: PageHandler::applySkin() читает $_COOKIE['skin'] напрямую (P2).
Ограничения: UI-менеджера скинов нет.

Low-Code и Компиляторы
Назначение: превращение JSON-структур в нативный PHP.

Классы:
PageCompiler: Безопасная компиляция JSON-дерева контента (h1, text, img, etc) в PHP-код (класс Page). Использует только безопасные методы Builder. Применяется через CLI page:write (для AI), а также для stub-страниц (domain:new, allias:new, structure:repair). withSchema=true оборачивает в схему.
RegionCompiler: JSON-дерево блока → PHP-класс. Требует флаг security.allow_php_blocks=true для генерации сырого PHP (custom/if).
SchemeCompiler: массив схемы → {name}.php (var_export). Вызывается через CLI scheme:save.

Инварианты:
Компиляторы вызываются только в момент сохранения (в админке через CLI или в CLI напрямую).
На выходе — быстрый PHP с вызовами Builder, без парсинга JSON на лету.
AI (через aicli) не может передать сырой PHP. Он передает только безопасный JSON, который компилируется PageCompiler.

Diagnostics (Диагностика)
Назначение: проверка целостности системы.

Классы:
StructureChecker: severity-aware проверка наличия всех 3 файлов для каждого элемента, стыковки филлер↔слот, отсутствия устаревших .rules.json.

Методы:
checkAllias(domain, allias): list<{level: string, message: string}>
  level: 'error' | 'warning'

Инварианты:
- error: отсутствует .php, отсутствует algoritm/algoritm.rules.json (root), отсутствует схема.
- warning: отсутствует .editor.json, отсутствует .rules.json у блока/схемы, устаревший {dir}/.rules.json (→ migrate:page-rules), филлер без слота.
- Для вложенных страниц используется classChain = algoritm_<rel_with_underscores>, а не basename(dir).
- Windows-разделители нормализуются в '/' перед вычислением classChain.

CLI structure:check:
- `$r->ok = true`, если нет errors (warnings допускаются).
- Вывод: `✗` для error, `⚠` для warning.
- Exit code: 0 при отсутствии errors, 1 при наличии errors.

CLI structure:repair:
- Восстанавливает отсутствующие root-страницы (algoritm.php через PageCompiler, algoritm.rules.json, algoritm.editor.json), algoritm.rules.json для схем, {name}.rules.json/.editor.json для блоков.
- Не трогает существующие страницы и настройки в site.config.
- Path traversal guard на domain (str_contains('..') + regex).

CLI, Админка и AI-Слой
Назначение: интерфейсы управления.

CLI: 24 команды, возвращают Result. Единственный источник истины для файловых операций.
Полный список: help, domain:new, domain:remove, allias:new, allias:remove, block:new, block:remove, filler:new, filler:toggle, page:new, page:toggle, page:write, page:read, scheme:new, scheme:copy, scheme:remove, scheme:save, db:migrate, admin:passwd, user:new, trash:clear, structure:check, structure:repair, migrate:page-rules.

Инварианты CLI:
- Destructive (domain:remove, allias:remove, block:remove, scheme:remove, trash:clear) — в карантин (`*_trash_<timestamp>/`), требуют --force.
- Path traversal guard: все команды с --domain/--name проверяют str_contains('..') и regex.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- realDomain: все команды, которые пишут в app/, строят путь по realDomain из site.config, а не по входному хосту.
- trash:clear — обход 4 уровней карантина (app/, app/<domain>/, .alliasscheme/, .alliasglobal/), symlink-safe rmrf.
- db:migrate --dir — ограничен подпапками migrations/ (realpath check).
- admin:passwd — валидация login (3-32 [a-zA-Z0-9_.-]) + password (≥8).
- user:new — проверка уникальности login и email + password (≥8).

Admin: Тонкий клиент. Контроллеры делегируют работу CLI-командам (включая scheme:copy/remove/save). CodeMirror для страниц и блоков. Визуальный Drag&Drop только для Схем (через CLI scheme:save). POST защищены CSRF.

AI-Слой (bin/aicli.php): Безопасный фасад для LLM. Принимает JSON-план, проверяет Whitelist, конвертирует JSON-блоки в base64 и вызывает bin/cli.php. Не имеет доступа к exec/system.

Разрешённые команды: read_test_file, engine_status, filler_toggle, domain:new, allias:new, page:new, page:write, page:read.

Запрещены: user:new, db:migrate, admin:passwd, domain:remove, allias:remove, block:remove, block:new, filler:new, trash:clear, migrate:page-rules, scheme:*.

Валидация аргументов:
- domain — должен быть в $allowedDomains (из config/agent.config).
- allias, name — regex [a-z][a-z0-9_]*.
- page:write: пустое name → __root__ (главная страница); --parent с __root__ — ошибка.
- page:write: domain проверяется по $allowedDomains (P0 закрыт — раньше проверки не было).

Инварианты:
Админка перехватывает фатальные ошибки (Safe Mode) и не падает при битом конфиге.
Агент не может выполнить запрещенные команды (защита на уровне фасада).
AI не может внедрить RCE (только безопасные JSON-блоки через PageCompiler).
page:write в aicli проверяет domain по $allowedDomains (иначе — RuntimeException).

Источник: bin/cli.php, bin/aicli.php, www/admin/index.php, src/Admin/

--- КОНЕЦ ДОКУМЕНТА ---