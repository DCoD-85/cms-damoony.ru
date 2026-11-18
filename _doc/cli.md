# CLI

Точка входа: `php bin/cli.php <command>`.  
Список команд: `php bin/cli.php help`.

## Базовое

```bash
php bin/cli.php cli:init --name=owner
php bin/cli.php db:migrate --force
php bin/cli.php health:check
php bin/cli.php structure:check
```

## Сайты и домены

| Команда | Назначение |
|---|---|
| `new` | создать проект из сборки (`--domain`, `--assembly`, `--force`) |
| `domain:new` | создать проект в `domains_app` + алиас `www` |
| `domain:list` | проекты и наличие папок |
| `domain:bind` | привязать хост к проекту |
| `domain:unbind` | снять хост |
| `domain:remove` | удалить проект в карантин (`--force`) |

```bash
php bin/cli.php new --domain=example.ru --assembly=minimal --force
php bin/cli.php domain:list
```

## Алиасы и языки

| Команда | Назначение |
|---|---|
| `allias:new` / `allias:list` / `allias:remove` | алиасы проекта |
| `lang:add` / `lang:list` / `lang:remove` | языки проекта/алиаса |
| `lang:sync` / `lang:check` / `lang:repair` | синхронизация и проверка переводов |

## Страницы

| Команда | Назначение |
|---|---|
| `page:list` | список страниц алиаса |
| `page:new` | создать страницу (3 файла) |
| `page:read` / `page:write` | чтение/запись PHP и JSON |
| `page:toggle` | флаги `auth`, `search`, `default` |

Обязательные аргументы команда сообщает сама: запустите без флагов.

## UI

| Команда | Назначение |
|---|---|
| `scheme:list/new/copy/remove/save` | схемы проекта |
| `block:list/new/write/edit/remove` | глобальные блоки |
| `filler:list/new/write/toggle` | локальные блоки алиаса |
| `skin:list/new/edit/remove` | скины алиаса |

## БД

| Команда | Назначение |
|---|---|
| `db:migrate` | применить миграции |
| `db:schema` | таблицы и колонки |
| `db:methods` / `db:describe` / `db:call` | read-only провайдеры |
| `db:mutate` | write-вызов (`--force=1`) |
| `dataprovider:add-method` | сгенерировать метод провайдера |

## Контент и бэкапы

| Команда | Назначение |
|---|---|
| `content:list/store/remove/gc` | файлы в `/content/` |
| `backup:create` / `backup:restore` | ZIP-бэкапы проекта |

## Админка

```bash
php bin/cli.php admin:create --login=root --password='strong-pass-1' --role=superadmin
php bin/cli.php admin:create --login=docs --password='strong-pass-2' --role=domainadmin --scope=example.ru
```

## Установки

```bash
php bin/cli.php install:create --account=client@example.ru --domain=client.ru --assembly=minimal
php bin/cli.php install:claim --token=... --domain=client.ru
php bin/cli.php install:approve --id=...
php bin/cli.php build:prune
```

## Persona/аккаунты

```bash
php bin/cli.php account:request --email=user@example.ru
php bin/cli.php account:approve --request=... --password=...
php bin/cli.php account:set-password --token=... --password=...
php bin/cli.php member:approve --account=... --project=... --alias=... --role=...
```

## Эксплуатация

| Команда | Назначение |
|---|---|
| `metrics:snapshot` | сводка метрик |
| `cron:run` | задачи обслуживания |
| `report:daily` | суточный отчёт |
| `alert:notify` | алерт в файл/webhook |
| `api:reflect` | стабы для IDE |
| `site:scan` | собрать `domains_app` из `app/*` |
| `site:config:migrate` | миграция старого `site.config` |
| `migrate:page-rules` | миграция `.rules.json` |

Точные флаги — в `php bin/cli.php help` и в сообщении команды без аргументов.
