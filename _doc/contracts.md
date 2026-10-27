--- НАЧАЛО ФАЙЛА _docrebuild/contracts.md ---
Контракты проекта Damoony

Документ фиксирует текущие архитектурные контракты (интерфейсы и границы ответственности) движка. Код реализаций скрыт за этими контрактами.

1. DomainResolver (Разрешение доменов)
Назначение: определение реального домена и алиаса по входящему хосту, предоставление контекста.

Методы:

resolve(string host): DomainContext

exists(string domain): bool

real(string domain): string

namespace(string domain): string

DomainContext (readonly):

domain: string (входной хост)

real: string (канонический домен, папка в app/)

allias: string (текущий алиас)

skin: string

language: string

available: bool (доступен ли домен И алиас)

stub: ?string

namespace: string (PHP-неймспейс)

scheme: string (имя схемы)

Инварианты:

Резолвер не бросает исключения, а возвращает контекст с available=false.

Отвечает только за вычисление контекста. Не загружает контроллеры и не рендерит страницы.

Источник: src/Domain/

2. Router (Маршрутизация)
Назначение: поиск класса контроллера по URL и контексту домена.

Методы:

match(string path, DomainContext domain): RouteMatch

RouteMatch (readonly):

found: bool

classChain: ?string (имя класса, например algoritm_about)

pathRel: ?string (относительный путь)

args: array (параметры из regex)

auth: bool (требуется ли авторизация)

code: int

Инварианты:

Роутер не выполняет код контроллера.

Роутер не рендерит ошибки (возвращает found=false и code).

Конфигурация правил хранится в .rules.json рядом с файлами страниц.

Источник: src/Routing/

3. Region (Регионы и Блоки)
Назначение: единица шаблона (шапка, подвал, сайдбар), которая вставляется в слоты схемы.

Методы:

name(): string

enabled(): bool

render(RegionContext context): Node

RegionRegistry (Управление регионами):

add(RegionInterface region)

disable(string name) / enable(string name)

useGlobal(string name) (игнорировать филлер)

get(string name): ?RegionInterface

Инварианты:

Регион не знает о других регионах и о схеме.

Регион возвращает объект Node, а не строку.

Загрузка филлеров и глобальных блоков контролируется RegionLoader на основе site.config.

Источник: src/Region/

4. UI (Дерево представления и Компоненты)
Назначение: типизированное дерево DOM, а не строки.

Базовые элементы:

Node (интерфейс)

Element (HTML-тег, атрибуты, дочерние элементы)

Attrs (безопасное управление атрибутами)

Builder (хелперы для быстрой сборки: div(), a(), p())

Document (корневой html, head, body)

Meta (управление title, CSS, JS)

Компоненты (Component):

Form (обёртка с action, method, enctype, CSRF)

Field (text, password, email, hidden, file, textarea, select, checkbox, radio)

Table (headers, rows, кастомные классы строк)

Button (submit, button)

Message (info, success, warning, error)

Инварианты:

Дерево строится мутабельно: append() / prepend() / content() меняют узел in-place. release() — финальный шаг, возвращает отрендеренную HTML-строку.

Автоматическое экранирование данных (XSS-защита) на уровне Attrs и Text.

Компоненты используются как на фронтенде, так и в админке.

Источник: src/Ui/

5. Scheme (Схемы)
Назначение: HTML-каркас страницы, собираемый из регионов с помощью слотов.

Методы:

SchemeLoader::load(DomainContext domain, string name): array

SchemeRenderer::render(array manifest, RegionRegistry regions, RegionContext context): Element

Манифест схемы (декларативный массив):

tag, class, children (вложенные теги).

slot (имя слота для вставки региона).

Иерархия схем:

Локальная схема алиаса (если включена).

Глобальная схема домена.

Если нет ни одной — ошибка 500 (скрытых дефолтов нет).

Инварианты:

Схема — это декларативный массив без бизнес-логики (хранимый в .data.php).

Схема не знает о содержимом регионов.

Источник: src/Scheme/

6. Auth (Авторизация и Безопасность)
Назначение: аутентификация и защита от подделки запросов.

Методы:

AuthInterface: login(), logout(), isAuthenticated(), user()

DbAuth (база данных)

Csrf: field(), check()

Инварианты:

Пароли хранятся только в виде password_hash.

Все POST-запросы (на фронтенде и в админке) защищены CSRF-токеном.

Флаг auth: true в .rules.json автоматически перенаправляет гостя на /login.

Источник: src/Auth/, src/Security/

7. CLI и Админка
Назначение: интерфейсы управления.

CLI: команды на базе CommandInterface, возвращают Result.

Admin: использует те же UI-компоненты для рендеринга. Включает BaseController, AdminRouter и встроенный редактор кода.

Инварианты:

Админка и CLI — равноправные способы управления.

Админка не дублирует бизнес-логику, а вызывает команды или использует общие сервисы.
--- КОНЕЦ ФАЙЛА _docrebuild/contracts.md ---