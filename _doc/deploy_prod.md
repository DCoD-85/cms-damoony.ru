# Прод-деплой рефакторинга

Статус до запуска: прод на старом коммите, `/ru/` отдаёт 500. Бэкап и тег `prod-before-refactor` есть.

## Что делает скрипт

`_dev/deploy/server_prod_deploy_refactor.sh`:

1. бэкап `config`, `content`, `var` и дамп БД в `/root/backups/prod-refactor-<дата>`;
2. `git fetch` + `reset --hard origin/refactor/site-config-v2`;
3. `composer install --no-dev`;
4. `db:migrate --force`;
5. `new --domain=damoony.ru --assembly=showcase --force` (чистые алиасы);
6. `security.session_domain = .damoony.ru`;
7. права (`www-data`, конфиги 640, `cli.identity` 600);
8. nginx-конфиги `persona/developer/admin` + reload;
9. `structure:check`, `health:check`, smoke по 4 хостам.

Запуск:

```bash
bash _dev/deploy/server_prod_deploy_refactor.sh        # покажет предупреждение
bash _dev/deploy/server_prod_deploy_refactor.sh --yes  # реальный деплой
```

## Nginx

Шаблоны: `_dev/deploy/nginx/prod-{persona,developer,admin}.conf`.

- все смотрят в `/var/www/damoony.ru/www`;
- wildcard-сертификат `*.damoony.ru` уже покрывает;
- `developer` и `admin` — `noindex`.

## После деплоя

- вход: `persona.damoony.ru` (нужен аккаунт с ролью persona-admin);
- админка: `admin.damoony.ru`;
- `build:prune` добавить в cron (очистка просроченных архивов);
- при откате: восстановить `/root/backups/prod-refactor-*` и `git reset --hard prod-before-refactor`.

## Клиент

После прода — установка `idoam.ru` через `install.sh` с `persona.damoony.ru`.
