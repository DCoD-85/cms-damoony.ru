--- НАЧАЛО ФАЙЛА _doc/architecture.md ---

Архитектура Damoony Engine

Движок построен по принципам Explicit over implicit и Contract-first.

Точки входа
www/index.php — Front Controller для публичного сайта.
www/admin/index.php — Front Controller для админки (обернут в try/catch для Safe Mode).
bin/cli.php — точка входа для консольных команд.

Жизненный цикл HTTP-запроса
Kernel: принимает Request, оборачивает выполнение в try/catch, логирует ошибки и возвращает Response.
Middleware: SecurityHeadersMiddleware (CSP, HSTS), RateLimitMiddleware, LanguageCookieMiddleware, SkinCookieMiddleware.
FrontController:

Вызывает DomainResolver::resolve(host).

Проверяет, зарегистрирован ли домен (если нет — 404).

Проверяет доступность (если false — заглушка 503 из stubs/).

Вызывает Router::match(path, domain). Проверяет Auth.

Создает RegionContext и передает управление PageHandler.
Router (FileRouter): Идет по дереву algoritm/, читает .rules.json, возвращает RouteMatch.
PageHandler: Загружает RegionRegistry, подключает PHP-файл страницы, вызывает default().
Algoritm (Страница): Возвращает объект Node (дерево UI) или Response.
Response: Оборачивает Node в Document и отдает HTML.

Слой домена (Domain)
Домен — папка app/<real_domain>/.
Алиас — папка внутри домена (www, doc).
Инфраструктурные папки: .alliasglobal/ (блоки) и .alliasscheme/ (схемы).

Слой UI и Компоненты
UI — типизированное дерево.
Node, Element, Attrs, Builder, Document, Meta.
Components — высокоуровневые блоки (Form, Field, Table) для админки.

Регионы, Слоты и Схемы
Region (RegionInterface): шапка, футер.
Slot: узел в схеме для вставки региона.
Scheme: SchemeRenderer собирает каркас из манифеста и слотов.
Иерархия: Локальная схема алиаса -> Глобальная схема домена.
Filler: переопределение региона для алиаса.

Low-Code и Компиляция (Визуальные редакторы)
Для удобства контент-менеджеров в движок встроен слой визуального редактирования.

Механика: Админка сохраняет структуру блоков в файл .edit.json рядом с PHP-файлом.

Компиляция: Классы PageCompiler (для страниц) и RegionCompiler (для блоков) читают JSON и генерируют быстрый PHP-код с вызовами Builder.

UI: Класс VisualEditorRenderer (использует Builder) генерирует формы для добавления/удаления и сортировки блоков (Drag-and-drop через JS).

Преимущество: Админ не пишет код, но посетитель получает чистый и быстрый PHP без оверхеда на парсинг JSON.

Слой данных
Только PDO. Connection (SQLite / MariaDB).
MigrationRunner применяет .sql и обрабатывает неявные коммиты MySQL.

Авторизация
DbAuth (password_verify).
Флаг auth в .rules.json проверяется FrontController.

CLI и Админка
CLI: Команды возвращают Result. Есть диагностика (structure:check) и обслуживание (trash:clear).
Admin: Использует UI-компоненты, CodeMirror, мультиязычность (RU/EN/ZH), профиль, менеджер заглушек, дашборд безопасности. Все POST защищены CSRF (PRG паттерн).

Диагностика и отказоустойчивость
StructureChecker: проверяет файлы, схемы и блоки (доступен в CLI и в админке).
Safe Mode: админка перехватывает фатальные ошибки и отдает понятную страницу.

Безопасность
Экранирование в Attrs и Text. Пароли только в password_hash.
CSP блокирует inline-скрипты (CodeMirror подключен локально).
CSRF-токены обязательны. Загрузка файлов ограничена whitelist.

--- КОНЕЦ ФАЙЛА _doc/architecture.md ---