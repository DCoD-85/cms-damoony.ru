--- НАЧАЛО ФАЙЛА _doc/readme.md ---

Damoony Engine
Легковесный PHP 8.4 движок для мультидоменных сайтов с собственной UI-моделью.

Что это
Damoony Engine — это переосмысление архитектуры SimplCMS. Он сохраняет философию старого проекта (домены как единица организации, UI как дерево, регионы как плагины, файловый роутинг), но реализован с нуля на современных стандартах.

Стек
PHP >= 8.4

Composer (PSR-4, PSR-3, PSR-11)

PHPUnit 10 (233 теста)

PHPStan (level 8)

MariaDB / MySQL (production) через PDO

Nginx + PHP-FPM (или Apache + mod_php)

Структура
bin/cli.php — точка входа CLI

config/ — JSON-конфиги (site.config, engine.config, admin.config)

src/ — исходный код (PSR-4, Damoony\)

lang/ — файлы переводов (ru.php, en.php, zh.php)

stubs/ — HTML-шаблоны для страниц 503 (Технические работы)

www/ — публичная директория (index.php, admin/index.php, media/)

app/<domain>/ — пользовательские домены (например, app/damoony.ru/)

migrations/ — SQL-миграции (для MariaDB)

var/ — runtime (БД, логи, кэш, бан-листы, архивы логов)

_dev/ — тесты, конфиги PHPUnit/PHPStan, инструменты

_doc/ — документация

Принципы
Домен — единица организации. Всё скоупится по домену и алиасу.

UI — это дерево. Представление строится как типизированное дерево узлов (Node, Element, Attrs).

Регионы, Слоты и Filler. Шаблон собирается из блоков (регионов), схемы используют слоты для их расстановки.

Управление из кода. Страницы могут управлять регионами на лету (disable, useGlobal, setContent).

Роутинг — файловые правила. Страницы ищутся по .rules.json в дереве algoritm/.

Один способ сделать что-то. Один слой БД (PDO), один роутер, один автозагрузчик.

Explicit over implicit. Нет $GLOBALS, статических кэшей, магии __get.

Контракт-first. Публичный API — интерфейсы (AuthInterface, RegionInterface и др.).

CLI и Админка — равноправные интерфейсы. Любое управление доступно и в консоли, и в UI.

Безопасность — основа. Пароли через password_hash, CSRF на POST, HttpOnly куки, защита от брутфорса.

Быстрый старт
Убедись, что в config/engine.config прописаны доступы к MariaDB, затем выполни:

bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=secret
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
Открой http://damoony3.ru/ (сайт) и http://damoony3.ru/admin/ (админка).
Страницы с "auth": true в .rules.json будут требовать входа через форму /login.

Подробнее: install.md, cli.md, architecture.md, guide.md, admin.md.

Статус
☑ Ядро: DI, PSR-3/11, Config, Env, ErrorHandler, Safe Mode (аварийный режим).
☑ HTTP: Routing (FileRouter), Domain (Resolver), Middleware (RateLimit, Security).
☑ UI: Node, Element, Attrs, Meta, Document, кастомные компоненты форм.
☑ Регионы: Slots, Fillers, Runtime Region Manager, HTML-заглушки.
☑ Auth: DbAuth, SessionAuth, CSRF, Login/Logout, логирование входов.
☑ DB: PDO, MigrationRunner (MariaDB / MySQL).
☑ CLI: 17+ команд (включая trash:clear, structure:check).
☑ Admin (UI/UX): Темная шапка, выпадающее меню профиля, динамический переключатель языков.
☑ Admin (Инструменты): CodeMirror (редактор кода с подсветкой), менеджер заглушек, дашборд безопасности (управление банами).
☑ Diagnostics: StructureChecker (валидация структуры через CLI и UI).
☑ I18n (Админка): Полный перевод (RU / EN / ZH).
☑ CI: GitHub Actions (PHPUnit на MariaDB, PHPStan, PHP-CS-Fixer).
[~] I18n (Фронтенд): инфраструктура заложена, но переключатель языка на самом сайте пока в разработке.

--- КОНЕЦ ФАЙЛА _doc/readme.md ---