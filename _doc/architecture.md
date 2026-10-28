--- НАЧАЛО ФАЙЛА _doc/architecture.md ---

Архитектура Damoony Engine

Движок построен по принципам Explicit over implicit и Contract-first. Ниже описано, как устроен жизненный цикл запроса и из каких слоев состоит система.

Точки входа
www/index.php — Front Controller для публичного сайта.
www/admin/index.php — Front Controller для админки (обернут в try/catch для Safe Mode).
bin/cli.php — точка входа для консольных команд.
Все они инициализируют контейнер (PSR-11), окружение (Env) и ядро (Kernel).

Жизненный цикл HTTP-запроса
Kernel: принимает Request, оборачивает выполнение в try/catch, логирует ошибки и возвращает Response.

Middleware: Kernel пропускает запрос через стек middleware. Базовые: SecurityHeadersMiddleware (CSP, HSTS), RateLimitMiddleware (защита от брутфорса), LanguageCookieMiddleware, SkinCookieMiddleware.

FrontController:

Вызывает DomainResolver::resolve(host), чтобы получить DomainContext (реальный домен, алиас, доступность домена и самого алиаса).

Проверяет, зарегистрирован ли домен в конфиге (если нет — отдает 404).

Проверяет доступность домена и алиаса (если false — загружает HTML-заглушку из папки stubs/ и отдает 503).

Вызывает Router::match(path, domain), чтобы найти маршрут. Если RouteMatch->auth равно true, проверяет авторизацию через AuthInterface и редиректит на /login при необходимости.

Создает RegionContext (содержит сервисы paths, site, auth, csrf, meta, i18n) и передает управление PageHandler.

Router (FileRouter): идет по дереву папок algoritm/ в app/<real_domain>/<allias>/, читает .rules.json на каждом шаге, сопоставляет сегменты URL и возвращает RouteMatch.

PageHandler: если роут найден, загружает RegionRegistry (через RegionLoader), подключает PHP-файл страницы, проверяет, что класс реализует AlgoritmInterface, инстанцирует его и вызывает метод default().

Algoritm (Страница): генерирует контент, возвращая объект Node (дерево UI) или Response. При необходимости управляет регионами (disable, useGlobal, setContent).

Response: если возвращен Node, PageHandler оборачивает его в Document (тег html) вместе с собранной мета-информацией и отдает HTML. Если возвращен Response — отдается как есть.

Слой домена (Domain)
Архитектура разделена на два уровня: Домен (инфраструктура) и Алиас (контент).
Домен — это папка в app/<real_domain>/. Входящие хосты мапятся на реальный домен через ConfigDomainResolver.
Внутри домена лежат алиасы (www, doc). Каждый алиас содержит algoritm/ (дерево страниц).
Инфраструктурные папки домена скрыты точкой:

.alliasglobal/ — глобальные блоки (регионы).

.alliasscheme/ — глобальные схемы (каркасы HTML).

Слой UI и Компоненты
UI — это типизированное дерево, а не строки.
Node — интерфейс любого узла.
Element — HTML-тег (тег, атрибуты, дочерние элементы).
Attrs — менеджер атрибутов с автоматическим экранированием (XSS-защита).
Builder — статические методы-хелперы.
Document — корневой контейнер (html, head, body).
Meta — менеджер ресурсов (title, CSS, JS).
Components — высокоуровневые UI-блоки (Form, Field, Table, Button, Message), используемые в админке.

Регионы, Слоты и Схемы
Region (Регион): интерфейс RegionInterface. Независимый блок контента (шапка, футер).
Slot (Слот): специальный узел, который в схеме указывает, куда вставлять регион.
Scheme (Схема): SchemeRenderer берет манифест, находит слоты, ищет регионы в RegionRegistry и собирает HTML-каркас.
Иерархия схем: 1) Локальная схема алиаса. 2) Глобальная схема домена. Если нет ни одной — 500.
Filler: переопределение региона для конкретного алиаса (например, top_doc вместо top).

Слой данных
Используется только PDO.
Connection — ленивая инициализация PDO (поддерживает SQLite и MariaDB/MySQL).
MigrationRunner — применение .sql файлов из migrations/ с фиксацией версий в таблице migrations. Обрабатывает неявные коммиты MySQL.

Авторизация
DbAuth: реализация AuthInterface на базе PDO (password_verify).
SessionAuth: базовая сессионная авторизация для тестов.
Роутинг: флаг auth в .rules.json заставляет FrontController проверять авторизацию перед вызовом страницы.

CLI и Админка
CLI: команды (CommandInterface), возвращают Result. Включает команды диагностики (structure:check) и обслуживания (trash:clear).
Admin: использует UI-компоненты. Включает CodeMirror (подсветка синтаксиса), мультиязычность (RU/EN/ZH), профиль администратора с логированием и сменой пароля, менеджер заглушек и дашборд безопасности. Все изменяющие действия (POST) защищены CSRF и используют паттерн PRG (Post/Redirect/Get).

Диагностика и отказоустойчивость
StructureChecker (src/Diagnostics): проверяет наличие обязательных файлов (.rules.json, algoritm.php), схем и блоков. Доступен через CLI и в админке (выводит предупреждения на странице «Домены»).
Safe Mode: в www/admin/index.php весь код инициализации обернут в try/catch. При критической ошибке (битый конфиг) отдается понятная HTML-страница с текстом ошибки, а не белый экран.

Безопасность
Входные данные экранируются на уровне Attrs и Text.
Пароли хранятся только как password_hash (bcrypt).
CSP (Content Security Policy) блокирует inline-скрипты (CodeMirror подключен локально из /media/admin/).
CSRF-токены обязательны для всех POST-запросов.
Загрузка файлов (Media) ограничена белым списком расширений.

--- КОНЕЦ ФАЙЛА _doc/architecture.md ---