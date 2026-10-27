--- НАЧАЛО ФАЙЛА _doc/readme.md ---

markdown
# Damoony Engine

Легковесный PHP 8.4 движок для мультидоменных сайтов с собственной UI-моделью.

## Что это
Damoony Engine — это переосмысление архитектуры SimplCMS. Он сохраняет философию старого проекта (домены как единица организации, UI как дерево, регионы как плагины, файловый роутинг), но реализован с нуля на современных стандартах.

## Стек
- PHP >= 8.4
- Composer (PSR-4, PSR-3, PSR-11)
- PHPUnit 10 (233 теста)
- PHPStan (level 8)
- **MariaDB / MySQL** (production) через PDO
- Nginx + PHP-FPM (или Apache + mod_php)

## Структура
- `bin/cli.php` — точка входа CLI
- `config/` — JSON-конфиги (`site.config`, `engine.config`, `admin.config`)
- `src/` — исходный код (PSR-4, `Damoony\`)
- `lang/` — файлы переводов (`ru.php`, `en.php`, `zh.php`)
- `www/` — публичная директория (`index.php`, `admin/index.php`, `media/`)
- `app/<domain>/` — пользовательские домены (например, `app/damoony.ru/`)
- `migrations/` — SQL-миграции (для MariaDB)
- `var/` — runtime (БД, логи, кэш, бан-листы)
- `_dev/` — тесты, конфиги PHPUnit/PHPStan, инструменты
- `_doc/` — документация

## Принципы
1. **Домен — единица организации.** Всё скоупится по домену и алиасу.
2. **UI — это дерево.** Представление строится как типизированное дерево узлов (`Node`, `Element`, `Attrs`).
3. **Регионы, Слоты и Filler.** Шаблон собирается из блоков (регионов), схемы используют слоты для их расстановки.
4. **Управление из кода.** Страницы могут управлять регионами на лету (`disable`, `useGlobal`, `setContent`).
5. **Роутинг — файловые правила.** Страницы ищутся по `.rules.json` в дереве `algoritm/`.
6. **Один способ сделать что-то.** Один слой БД (PDO), один роутер, один автозагрузчик.
7. **Explicit over implicit.** Нет `$GLOBALS`, статических кэшей, магии `__get`.
8. **Контракт-first.** Публичный API — интерфейсы (`AuthInterface`, `RegionInterface` и др.).
9. **CLI и Админка — равноправные интерфейсы.** Любое управление доступно и в консоли, и в UI.
10. **Безопасность — основа.** Пароли через `password_hash`, CSRF на POST, HttpOnly куки, защита от брутфорса.

## Быстрый старт
Убедись, что в `config/engine.config` прописаны доступы к MariaDB, затем выполни:
```bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=secret
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
Открой http://damoony3.ru/ (сайт) и http://damoony3.ru/admin/ (админка).
Страницы с "auth": true в .rules.json будут требовать входа через форму /login.

Подробнее: install.md, cli.md, architecture.md, guide.md, admin.md.

Статус
☑ Ядро (DI, PSR-3/11, Config, Env, ErrorHandler)
☑ HTTP, Routing (FileRouter), Domain (Resolver)
☑ UI (Node, Element, Attrs, Meta, Document)
☑ UI-компоненты (Form, Field, Table, Button, Message)
☑ Регионы (Slots, Fillers, Runtime Region Manager)
☑ Auth (DbAuth, SessionAuth, CSRF, Login/Logout)
☑ DB (PDO, MigrationRunner) — MariaDB / MySQL
☑ CLI (16 команд, включая trash:clear)
☑ Admin: Красивая темная шапка, выпадающее меню профиля, встроенный редактор кода.
☑ I18n (Админка): Полный перевод (RU / EN / ZH), динамический список языков из admin.config.
☑ Профиль: Сводка, безопасность (смена пароля) и логирование входов (IP, время).
☑ Skins: Автоподключение main.css/main.js активного скина через SkinManager.
☑ CI: GitHub Actions (PHPUnit, PHPStan, PHP-CS-Fixer).
[~] I18n (Фронтенд): инфраструктура заложена, но переключатель языка на самом сайте пока в разработке.

text
--- КОНЕЦ ФАЙЛА _doc/readme.md ---