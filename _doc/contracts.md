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
Правила — в algoritm_{name}.rules.json рядом со страницами.

Ограничения:
Regex-параметры формата regex|target; @preg_match глушит ошибки — битый regex даст тихий notFound, не диагностику.
RouteMatch::forbidden() — мёртвый код.

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
Источник истины — папки .alliasglobal/*/, а не глобальные манифесты (blocks.json удален).

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
Визуальный редактор (Drag&Drop) сохраняет вложенность и обновляет editor.json, а затем компилирует в php.

Источник: src/Scheme/

Auth (Авторизация и Безопасность)
Назначение: аутентификация и защита от подделки запросов.

Методы:
AuthInterface: login(), logout(), isAuthenticated(), user()
DbAuth (база данных)
Csrf: field(), check()
Algoritm (Базовый класс): isAuthenticated(): bool, user(): mixed

Инварианты:
Пароли — только password_hash.
Все POST защищены CSRF. Без исключений.
Флаг auth: true в .rules.json автоматически перенаправляет гостя на /login.
Все destructive требуют POST+CSRF. UI переведён на POST-формы.
Админка использует отдельную авторизацию (config/admin.config), независимую от пользователей сайта.

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
RegionCompiler: JSON-дерево блока → PHP-класс. Требует флаг security.allow_php_blocks=true для генерации сырого PHP (custom/if).
SchemeCompiler: массив схемы → {name}.php (var_export).

Инварианты:
Компиляторы вызываются только в момент сохранения (в админке или CLI).
На выходе — быстрый PHP с вызовами Builder, без парсинга JSON на лету.
PageCompiler удален. Страницы редактируются только через CodeMirror (ручной PHP).

Diagnostics (Диагностика)
Назначение: проверка целостности системы.

Классы:
StructureChecker: проверяет наличие всех 3 файлов (.php, .rules.json, .editor.json) для каждого элемента, стыковку филлер↔слот.

Инварианты:
Результат — массив (level, message); выводится в CLI (structure:check) и в админке (System Health).
Проверяет соответствие структуры правилу 3 файлов.

Источник: src/Diagnostics/

CLI, Админка и AI-Слой
Назначение: интерфейсы управления.

CLI: 22 команды, возвращают Result. Включает диагностику (structure:check), восстановление (structure:repair) и обслуживание. Идемпотентен. Единственный источник истины для файловых операций.

Admin: Тонкий клиент. Контроллеры делегируют работу CLI-командам (SchemeRemoveCommand, SchemeSaveCommand). CodeMirror для страниц и блоков. Визуальный Drag&Drop только для Схем. POST защищены CSRF.

AI-Слой (bin/aicli.php): Безопасный фасад для LLM. Принимает JSON, проверяет Whitelist, вызывает bin/cli.php. Не имеет доступа к exec/system.

Инварианты:
Админка перехватывает фатальные ошибки (Safe Mode) и не падает при битом конфиге.
Агент не может выполнить запрещенные команды (защита на уровне фасада).

Источник: bin/cli.php, bin/aicli.php, www/admin/index.php, src/Admin/

--- КОНЕЦ ДОКУМЕНТА ---