# Damoony Engine

PHP 8.5 движок для мультидоменных сайтов: файловый routing, проекты/алиасы, схемы и блоки, i18n, CLI-first, серверная админка.

## Требования

- PHP ≥ 8.5 (`pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `curl`, `gd`);
- Composer;
- MariaDB 11+ / MySQL 8+;
- Nginx + PHP-FPM (рекомендуется);
- Python 3.10+ — только для AI-сценариев (сейчас отложены).

## Быстрый старт

```bash
git clone https://github.com/DCoD-85/cms-damoony.ru.git damoony
cd damoony
composer install
# создать config/engine.config (пример — в _doc/install.md)
php bin/cli.php cli:init --name=owner
php bin/cli.php db:migrate
php bin/cli.php new --domain=example.ru --assembly=minimal --force
php bin/cli.php admin:create --login=root --password='strong-pass-1' --role=superadmin
php bin/cli.php health:check
```

Сайт: `http://example.ru/ru/`  
Админка: `http://127.0.0.1/ru/` (см. `_doc/admin.md`).

## Документация

| Файл | О чём |
|---|---|
| `_doc/install.md` | установка с нуля |
| `_doc/admin.md` | админка, роли, доступ |
| `_doc/deploy.md` | staging/prod, nginx, php-fpm |
| `_doc/cli.md` | команды CLI |
| `_doc/local.md` | локальная среда |

## Проверки

```bash
vendor/bin/phpunit -c _dev/phpunit.xml
vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
php bin/cli.php structure:check
php bin/cli.php health:check
```

## Статус

- ветка: `refactor/site-config-v2`;
- админка: MVP (superadmin/domainadmin), `apps/` — следующий этап;
- AI-вход и AI-редактор — отложены.
