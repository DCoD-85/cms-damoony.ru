--- НАЧАЛО ФАЙЛА ---

# Damoony Engine

Легковесный PHP 8.4 движок для мультидоменных сайтов с собственной UI-моделью.

## Что это

Damoony Engine — это переосмысление архитектуры SimplCMS. Он сохраняет философию старого проекта (домены как единица организации, UI как дерево, регионы как плагины, файловый роутинг), но реализован с нуля на современных стандартах.

## Стек

- PHP >= 8.4
- Composer (PSR-4, PSR-3, PSR-11)
- PHPUnit 10
- PHPStan (level 8)
- SQLite (dev) / MySQL (prod) через PDO
- Nginx + PHP-FPM (или Apache + mod_php)

## Структура

- `bin/cli.php` — точка входа CLI
- `config/` — JSON-конфиги (`site.config`, `engine.config`, `admin.config`)
- `src/` — исходный код (PSR-4, `Damoony\`)
- `www/` — публичная директория (`index.php`, `admin/index.php`, `media/`)
- `app/<domain>/` — пользовательские домены (например, `app/damoony.ru/`)
  - `app/<domain>/<allias>/algoritm/` — страницы алиасов (`www`, `doc`, `admin`)
  - `app/<domain>/.alliasglobal/` — глобальные блоки домена (скрытая папка)
  - `app/<domain>/.alliasscheme/` — глобальные схемы домена (скрытая папка)
- `migrations/` — SQL-миграции
- `var/` — runtime (БД, логи, кэш, бан-листы)
- `_dev/` — тесты, конфиги PHPUnit/PHPStan, инструменты
- `_doc/` — документация

## Принципы

1. **Домен — единица организации.** Всё скоупится по домену и алиасу.
2. **UI — это дерево.** Представление строится как типизированное дерево узлов (`Node`, `Element`, `Attrs`), а не строковый шаблон.
3. **Регионы, Слоты и Filler.** Шаблон собирается из блоков (регионов). Схемы используют слоты для их расстановки. Filler'ы позволяют переопределять блоки для конкретных алиасов.
4. **Управление из кода.** Страницы могут управлять регионами на лету (`disable`, `useGlobal`, `setContent`), скрывая или подменяя блоки.
5. **Роутинг — файловые правила.** Страницы ищутся по `.rules.json` в дереве `algoritm/`.
6. **Один способ сделать что-то.** Один слой БД (PDO), один роутер, один автозагрузчик (Composer PSR-4).
7. **Explicit over implicit.** Нет `$GLOBALS`, статических кэшей, магии `__get`.
8. **Контракт-first.** Публичный API — интерфейсы (`AuthInterface`, `RegionInterface` и др.).
9. **CLI и Админка — равноправные интерфейсы.** Любое управление доступно как в CLI, так и в визуальном интерфейсе (с использованием собственных UI-компонентов и встроенного редактора кода).
10. **Безопасность — основа.** Пароли через `password_hash`, база данных для пользователей, CSRF на POST, HttpOnly куки, защита от брутфорса.

## Быстрый старт

```bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=secret
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
```

Открой `http://damoony3.ru/` (сайт) и `http://damoony3.ru/admin/` (админка).
Страницы с `"auth": true` в `.rules.json` будут требовать входа через форму `/login`.

Подробнее: `INSTALL.md`, `CLI.md`, `ARCHITECTURE.md`, `GUIDE.md`, `ADMIN.md`.

## Статус

- [x] Ядро (DI, PSR-3/11, Config, Env, ErrorHandler)
- [x] HTTP, Routing (FileRouter), Domain (Resolver)
- [x] UI (Node, Element, Attrs, Meta, Document)
- [x] UI-компоненты (Form, Field, Table, Button, Message)
- [x] Регионы (Slots, Fillers, Runtime Region Manager)
- [x] Auth (DbAuth, SessionAuth, CSRF, Login/Logout)
- [x] DB (PDO, MigrationRunner)
- [x] CLI (14 команд)
- [x] Admin (Древовидная навигация Домен/Алиас, CRUD, управление привязками хостов, медиа-менеджер, встроенный редактор кода)

--- КОНЕЦ ФАЙЛА ---