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
| [`_doc/install.md`](_doc/install.md) | установка с нуля |
| [`_doc/arch.md`](_doc/arch.md) | архитектура: домены, routing, pages, UI |
| [`_doc/ui.md`](_doc/ui.md) | страницы, схемы, блоки, AJAX |
| [`_doc/admin.md`](_doc/admin.md) | админка, роли, доступ |
| [`_doc/content.md`](_doc/content.md) | контент-админка, JSON-блоки |
| [`_doc/deploy.md`](_doc/deploy.md) | staging/prod, nginx, php-fpm |
| [`_doc/cli.md`](_doc/cli.md) | команды CLI |
| [`_doc/local.md`](_doc/local.md) | локальная среда |
| [`_doc/troubleshooting.md`](_doc/troubleshooting.md) | типовые проблемы |

## Проверки

```bash
vendor/bin/phpunit -c _dev/phpunit.xml
vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
php bin/cli.php structure:check
php bin/cli.php health:check
```

## Разработка

1. Ветка от `refactor/site-config-v2`, без force-push.
2. Миграции — только новым файлом в `migrations/`, не править применённые.
3. Перед PR:

```bash
vendor/bin/phpunit -c _dev/phpunit.xml
vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run
php bin/cli.php structure:check
php bin/cli.php health:check
```

4. Документация — в `_doc/`, без исторических описаний.

## Статус

- ветка: `refactor/site-config-v2`;
- админка: MVP (superadmin/domainadmin), `apps/` — следующий этап;
- AI-вход и AI-редактор — отложены.
