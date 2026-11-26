# Troubleshooting

## 404 на сайте

1. Хост есть в `config/site.config` → `mirrors`?
2. Алиас существует и `available: true`?
3. `folder` проекта совпадает с папкой в `app/`?
4. `php bin/cli.php domain:list`
5. `php bin/cli.php structure:check`

Проверить конкретный хост:

```bash
curl -I -H 'Host: example.ru' http://127.0.0.1/ru/
```

## 500

- локально: создать `_dev/.dev` — ошибка будет в HTML; удалить после;
- прод: `var/log/` (php/app), `journalctl -u php8.5-fpm`, nginx error log;
- проверить `config/engine.config` (JSON, БД);
- `php bin/cli.php health:check`.

## «Технические работы»

Stub включается, если проект/алиас/зеркало недоступны.

- проверить `available` в `site.config`;
- **не отключать текущий хост** — для `admin` защита уже есть;
- вернуть вручную:

```bash
php bin/cli.php domain:list
# отредактировать site.config или использовать админку
```

## Админка 404/не пускает

- IP — в `security.admin_ips`; пустой список = только `127.0.0.1`;
- нет сессии — откроется форма входа;
- `domainadmin` не имеет доступа к `/projects`, `/accounts`, `/ops`, `/audit` — это норма;
- проверить cookie `ADMIN_SESSID` и `ADMIN_CSRF`.

## CSRF token mismatch

- для страницы с собственным токеном: `"csrf": false` в `.rules.json`;
- токен должен приходить из cookie и hidden-поля;
- после смены пароля старые сессии отозваны — войти заново.

## Nginx отдаёт PHP как файл / 404

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
}
```

Проверить:

```bash
nginx -t
systemctl reload nginx
php -v
```

## Ошибки прав

```bash
chown -R www-data:www-data app www/media var content
chown root:www-data config/engine.config config/site.config
chmod 640 config/engine.config
chmod 660 config/site.config
chmod 600 var/cli.identity
```

Если админка не может переключить алиас — проверить запись в `site.config` группой `www-data`.

## БД

```bash
mysql -e 'SELECT 1'
php bin/cli.php db:migrate --force
php bin/cli.php db:schema
```

## Тесты/стиль

```bash
vendor/bin/phpunit -c _dev/phpunit.xml
vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run
```
