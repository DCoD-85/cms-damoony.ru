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
- `app/` — пользовательские домены, алиасы, страницы, схемы
- `migrations/` — SQL-миграции
- `var/` — runtime (БД, логи, кэш, бан-листы)
- `_dev/` — тесты, конфиги PHPUnit/PHPStan, инструменты
- `_doc/` — документация

## Принципы

1. **Домен — единица организации.** Всё скоупится по домену и алиасу.
2. **UI — это дерево.** Представление строится как типизированное дерево узлов (`Node`, `Element`, `Attrs`), а не строковый шаблон.
3. **Регионы и filler — плагины шаблона.** Шаблон домена состоит из именованных регионов (`top`, `center`, `footer`).
4. **Роутинг — файловые правила.** Страницы ищутся по `.rules.json` в дереве `algoritm/`.
5. **Один способ сделать что-то.** Один слой БД (PDO), один роутер, один автозагрузчик (Composer PSR-4).
6. **Explicit over implicit.** Нет `$GLOBALS`, статических кэшей, магии `__get`.
7. **Контракт-first.** Публичный API — интерфейсы.
8. **CLI — первый класс.** Любое управление доступно в CLI, админка — надстройка.
9. **Безопасность — основа.** Пароли через `password_hash`, CSRF на POST, куки с `HttpOnly` и `SameSite`, защита от brute-force.
10. **Идемпотентность.** Команды можно запускать повторно без побочных эффектов.

## Быстрый старт

```bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=secret
```

Открой `http://damoony3.ru/` (сайт) и `http://damoony3.ru/admin/` (админка).

Подробнее: `INSTALL.md`, `CLI.md`, `ARCHITECTURE.md`, `GUIDE.md`, `ADMIN.md`.

## Статус

- [x] Ядро, HTTP, Routing, Domain, UI, Region, Scheme, Algoritm, I18n, Db
- [x] CLI (13 команд)
- [x] Admin (Auth, CRUD: домены, алиасы, блоки, страницы, схемы, filler'ы, медиа, настройки)