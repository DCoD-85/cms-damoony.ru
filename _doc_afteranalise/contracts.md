--- НАЧАЛО ДОКУМЕНТА ---

Контракты проекта Damoony
Документ фиксирует текущие архитектурные контракты (интерфейсы и границы ответственности). Код реализаций скрыт за этими контрактами. Термины — по MANIFEST.md §2. Известные расхождения контракта и поведения — по MANIFEST.md §6–7.

Принцип: если интерфейс разрешает что-то, а runtime это ломает — это баг контракта, а не «особенность». См. §6.18 (default(): null).

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

allias: string — текущий алиас

logic: string — мёртвое поле: вычисляется (дефолт alpha.0.0), нигде не читается (§6.21, P2)

skin: string

language: string

available: bool — доступен ли домен И алиас

stub: ?string

namespace: string — PHP-namespace

scheme: string — имя схемы; используется в PageCompiler/HomePage, но не в PageController::edit() (§6.7, P2)

Инварианты:

Резолвер не бросает исключений → DomainContext с available=false.

Отвечает только за вычисление контекста. Не загружает контроллеры и не рендерит страницы.

Ограничения (P0/P1):

Порт и trailing-dot не нормализуются (§6.17): 192.168.1.10:8080 не матчится с 192.168.1.10. Критично для IP-деплоя с портом.

domains_real[$domain] работает только при self-mapping ("example.ru": "example.ru"). Свежий домен без self-mapping → Undefined array key (§6.4, P2).

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

guest: bool — не используется в FrontController

Инварианты:

Роутер не выполняет код контроллера.

Роутер не рендерит ошибки → RouteMatch с found=false и code.

Правила — в .rules.json рядом со страницами.

Regex-параметры формата regex|target; @preg_match глушит ошибки — битый regex даст тихий notFound, не диагностику.

Мёртвый код: RouteMatch::forbidden() — не вызывается (P2, MANIFEST.md §7.3 п.6).

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

useGlobal(string $name) — игнорировать филлер

useFiller(string $name) — обратное

get(string $name): ?RegionInterface — приоритет: disabled → forcedGlobal → filler → base

has(string $name): bool

all(): array

names(): array

Инварианты:

Регион не знает о других регионах и о схеме. Возвращает Node, не строку.

Типы блоков: ui (визуализация), logic (скрипт), mixed (реализация). blocks.json записывается CLI, но не читается RegionLoader (§4) — источник истины — папки.

.data.php блоков не читаются движком (§4). Данные — в PHP-классе.

Ограничения:

RegionContext::service(string): mixed — Service Locator. @template в PHPDoc отсутствует (§6.2) — компенсация только через instanceof на стороне вызывающего.

RegionLoader молча пропускает битые блоки (класс не реализует RegionInterface) — ни лога, ни ошибки. Диагностика только через structure:check.

Источник: src/Region/

UI (Дерево представления и Компоненты)
Назначение: типизированное дерево DOM, не строки.

Базовые элементы: Node (интерфейс), Element (HTML-тег), Attrs (атрибуты), Builder (хелперы, 12 методов), Document (html/head/body), Meta (title/CSS/JS), Text, Fragment, Group.

Компоненты (Component): Form, Field, Table, Button, Message.

Инварианты:

Дерево мутабельно: append() / prepend() / content() меняют узел in-place. release() возвращает HTML-строку.

Экранирование данных — на уровне Attrs и Text (htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)). Исключение: имена атрибутов в Attrs::release() не валидируются (§7.2 п.2, P1) — не передавайте имена из пользовательских данных.

Node — контракт с изъяном: Text::append(), Slot::append(), Slot::attrs() бросают LogicException. Код, работающий с Node generic-но, не может безопасно вызвать append. Разделение на Node / ParentNode — не сделано (P2).

Slot::release() возвращает '' — слот без региона молча исчезает. Диагностика — только через structure:check.

Group дублирует Element — мёртвый груз (§7.3 п.6).

Builder неполон (нет table, form, section, nav, label, select, textarea) — сложные структуры через new Element() или компоненты (§6.6).

Ограничения:

Document::release() не идемпотентен — повторный вызов дублирует <title>/<link>/<script> (§7.2 п.1, P1). В обычном пайплайне Document создаётся один раз в PageHandler.

Источник: src/Ui/

Scheme (Схемы)
Назначение: HTML-каркас страницы, собираемый из регионов через слоты.

Методы:

SchemeLoader::load(DomainContext $domain, string $schemeName = 'www'): array

SchemeRenderer::render(array $manifest, RegionRegistry $regions, RegionContext $context): Element

Манифест схемы (декларативный массив): tag, class, children (вложенные теги), slot (имя слота).

Иерархия схем:

Локальная схема алиаса (если включена).

Глобальная схема домена (обязательна).

Если нет ни одной — 500. Инвариант «скрытых дефолтов нет».

Расхождение (P1, §6.5): SchemeLoader::load() всегда возвращает fallback div.layout > main[slot=center], если схемы нет. Инвариант нарушен. Требуется if (!$env->isDev()) throw.

Инварианты:

Схема — декларативный массив без бизнес-логики (хранится в .data.php).

Схема не знает о содержимом регионов.

Ограничения:

SchemeCompiler::compile() использует var_export + str_replace('array (', '['). Ломается на любом тексте/классе со скобкой (§7.1 п.5, P0). Требуется round-trip тест.

SchemeController::edit() схлопывает вложенные схемы в плоский div.layout (§6.15, P1). Вложенность сохраняется только при редактировании через CodeMirror.

schemes.json может рассинхронизироваться с файлами на диске — «сироты» (пример: visuo.data.php без записи в schemes.json). StructureChecker это не ловит (§7.2 п.3, P1).

Источник: src/Scheme/

Auth (Авторизация и Безопасность)
Назначение: аутентификация и защита от подделки запросов.

Методы:

AuthInterface: login(), logout(), isAuthenticated(), user()

DbAuth (база данных)

Csrf: field(), check()

Инварианты:

Пароли — только password_hash.

Все POST защищены CSRF. Без исключений.

Флаг auth: true в .rules.json автоматически перенаправляет гостя на /login.

Нарушение (P0, §7.1 п.1): Destructive GET — SchemeController::delete/copy, BlockController::remove изменяют данные через GET без CSRF. Инвариант «destructive — только POST» нарушен. MANIFEST.md §8.2 п.1.

Админка использует отдельную авторизацию (config/admin.config), независимую от пользователей сайта.

Источник: src/Auth/, src/Security/, src/Admin/Auth.php

I18n (Мультиязычность)
Назначение: перевод интерфейса админки и сайта.

Классы:

Translator: принимает currentLang, fallbackLang, путь к словарям. Метод t(key, params).

LanguageNegotiator: определяет язык (URL → Cookie → Конфиг).

Хранилище: файлы lang/{code}.php в корне проекта.

Инварианты: список доступных языков передаётся в View динамически (из admin.config). При отсутствии перевода — fallback.

Ограничения:

Фронтенд: инфраструктура (middleware, DomainContext->language) есть, UI-переключателя на сайте и ключей site.* нет (MANIFEST.md §4, 🟡). hreflang не реализован.

FrontController создаёт Translator/LanguageNegotiator вручную, минуя контейнер (§6.19, P2).

Источник: src/I18n/, lang/

Skins (Скины)
Назначение: управление визуальными темами.

Классы:

SkinManager: сканирует www/media/<domain>/skin/, проверяет наличие скина, возвращает пути к main.css и main.js.

Инварианты:

PageHandler автоматически добавляет main.css/main.js активного скина в Meta.

Скин валидируется через cookie skin и domains_skins_allowed из site.config.

Расхождение: PageHandler::applySkin() читает $_COOKIE['skin'] напрямую, минуя SkinCookieMiddleware (§6.20, P2). Валидация allowed в PageHandler дублирует middleware.

Ограничения: UI-менеджера скинов в админке и переключателя на фронте нет (MANIFEST.md §4, 🟡).

Источник: src/Skin/

Low-Code и Компиляторы
Назначение: превращение визуальных JSON-структур в нативный PHP.

Классы:

PageCompiler: JSON-дерево страницы → PHP-класс (algoritm_*.php).

RegionCompiler: JSON-дерево блока → PHP-класс (alliasglobal_*.php).

SchemeCompiler: массив схемы → .data.php (с str_replace — см. §Scheme).

Инварианты:

Компиляторы вызываются только в момент сохранения в админке.

На выходе — быстрый PHP с вызовами Builder, без парсинга JSON на лету.

Нарушения (P0):

PageCompiler/RegionCompiler используют addslashes() — ломается на ', \, $, \n (§7.1 п.2). Нужен var_export($content, true).

Два формата .edit.json ({blocks} старый, {slots} новый) не мигрированы (§7.1 п.4). Первое визуальное сохранение старой страницы затрёт контент.

Генерируемый PageCompiler код сам вызывает SchemeLoader + SchemeRenderer — это компенсирует §6.1 для визуальных страниц, но усиливает разницу с ручными.

Источник: src/Page/, src/Region/, src/Scheme/

Diagnostics (Диагностика)
Назначение: проверка целостности системы.

Классы:

StructureChecker: проверяет наличие .rules.json, algoritm.php, схем, глобальных блоков, стыковку филлер↔слот.

Инварианты:

Результат — массив (level, message); выводится в CLI (structure:check) и в админке (System Health).

Пробелы (P1, §7.2 п.3):

Не проверяет .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки, schemes.json ↔ файлы.

«Сироты» (файлы без записи в манифесте и наоборот) не ловятся. Пример: visuo.data.php без записи в schemes.json.

Источник: src/Diagnostics/

CLI и Админка
Назначение: интерфейсы управления.

CLI: 17 команд, возвращают Result. Включает диагностику и обслуживание. Идемпотентен.

Admin: UI-компоненты, CodeMirror (локальный), мультиязычность (RU/EN/ZH), профиль, менеджер заглушек, дашборд безопасности. POST защищены CSRF, PRG. Destructive GET — не защищены (P0).

Инварианты:

Админка и CLI — равноправные способы управления.

Админка перехватывает фатальные ошибки (Safe Mode) и не падает при битом конфиге.

Ограничения:

«100% паритет CLI/UI» — неверно. Только CLI: db:migrate, structure:check (в UI есть, но вывод иной), vars:extract, trash:clear, user:new. Только UI: editor (CodeMirror), визуальный редактор блоков/схем/страниц. MANIFEST.md §3.

PageController::save() и BlockController::save() читают domains_real[$domain] — работает при self-mapping; без него — Undefined array key (§6.4, P2).

PageController::edit() читает domains_scheme[$domain] — для входящего хоста (например, damoony3.ru) схема не найдётся, слоты в редакторе пустые (§6.16, P1).

Источник: bin/cli.php, www/admin/index.php, src/Admin/

--- КОНЕЦ ДОКУМЕНТА ---