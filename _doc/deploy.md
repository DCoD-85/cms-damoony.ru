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

## Сертификаты

- основной домен и wildcard: `*.damoony.ru`;
- IP-админка: самоподписанный или отдельный домен — wildcard **не покрывает IP**;
- обновление: `certbot renew --deploy-hook "systemctl reload nginx"`;
- проверить: `openssl s_client -connect <IP>:443 -servername <IP>`.

## Cron

```cron
17 3 * * * cd /var/www/damoony.ru && php bin/cli.php build:prune >> var/log/cron.log 2>&1
5 6 * * * cd /var/www/damoony.ru && php bin/cli.php report:daily >> var/log/cron.log 2>&1
```

## Логи и мониторинг

- `var/log/` — CLI, cron, запросы;
- `/var/log/nginx/*.log`;
- `journalctl -u php8.5-fpm`;
- `php bin/cli.php health:check`;
- `php bin/cli.php metrics:snapshot --since=24h`;
- алерты: `alert:notify` + webhook.

## Бэкапы

- БД: `mysqldump --single-transaction`;
- файлы: `config`, `content`, `app`, `www/media`;
- хранение: ежедневно 14 дней, еженедельно 8 недель;
- проверка восстановления — раз в месяц на staging.

## Чек-лист деплоя

- [ ] бэкап БД и конфигов;
- [ ] `git reset --hard` на нужный commit;
- [ ] `composer install --no-dev`;
- [ ] `db:migrate --force`;
- [ ] `structure:check` / `health:check`;
- [ ] права на `app`, `www/media`, `var`, `content`;
- [ ] `security.admin_ips` и IP-vhost;
- [ ] smoke: сайт, persona, developer, админка;
- [ ] логи без ошибок;
- [ ] cron/бэкап-задачи на месте.

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
