# Деплой

## Общее

- код: `/var/www/damoony.ru` (prod), `/var/www/staging.damoony.ru` (staging);
- ветка: `refactor/site-config-v2`;
- nginx + php8.5-fpm + MariaDB;
- конфиги и БД — вне git, бэкапятся отдельно.

## Staging

```bash
cd /var/www/staging.damoony.ru
git fetch origin
git reset --hard origin/refactor/site-config-v2
composer install --no-dev --no-interaction
php bin/cli.php db:migrate --force
php bin/cli.php new --domain=damoony.ru --assembly=minimal --force
php bin/cli.php structure:check
php bin/cli.php health:check
chown -R www-data:www-data app www/media var content
chown root:root config/engine.config config/site.config
chmod 640 config/engine.config config/site.config
```

Проверка:

```bash
curl -I https://staging.damoony.ru/ru/
php bin/cli.php domain:list
```

## Prod

⚠️ Прод-деплой — только после бэкапа и явного подтверждения.

```bash
# бэкап
cd /var/www/damoony.ru
tar czf /root/backups/config-$(date +%F-%H%M).tgz config
mysqldump --single-transaction www_damoony | gzip > /root/backups/db-$(date +%F-%H%M).sql.gz

# код
git fetch origin
git reset --hard origin/refactor/site-config-v2
composer install --no-dev --no-interaction

# БД и структура
php bin/cli.php db:migrate --force
php bin/cli.php structure:check
php bin/cli.php health:check
```

Админка на проде:

1. добавить проект `admin` в `config/site.config` с зеркалом IP сервера;
2. `security.admin_ips` — рабочие IP;
3. отдельный nginx-vhost на IP (см. `_dev/deploy/nginx/prod-admin-ip.conf`);
4. отдельный php-fpm pool (`_dev/deploy/php-fpm/admin.conf`);
5. `php bin/cli.php admin:create ...`.

Nginx-шаблоны:

- `_dev/deploy/nginx/prod-admin-ip.conf` — админка на IP;
- `_dev/deploy/nginx/prod-persona.conf`, `prod-developer.conf` — алиасы.

Проверка после деплоя:

```bash
curl -I https://damoony.ru/ru/
curl -I https://persona.damoony.ru/ru/
curl -I https://<IP>/ru/
php bin/cli.php structure:check
php bin/cli.php health:check
```

## Откат

```bash
git reset --hard <previous-commit>
tar xzf /root/backups/config-<...>.tgz -C /
gunzip -c /root/backups/db-<...>.sql.gz | mysql www_damoony
```

## Ограничения

- 2FA и отдельная auth DB — не внедрены;
- админка на IP требует сертификат (wildcard `*.damoony.ru` не покрывает IP);
- `build:prune` добавить в cron после стабилизации.
