# Быстрый старт

От пустого сервера до работающего сайта — команды по порядку.

## 1. Сервер (Debian 13 / Ubuntu 24.04)

```bash
# базовые пакеты
apt update
apt install -y nginx mariadb-server git curl unzip build-essential \
    libxml2-dev libssl-dev libsqlite3-dev libcurl4-openssl-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libicu-dev

# PHP 8.5 собираем из исходников (пример: 8.5.x)
cd /usr/local/src
curl -LO https://www.php.net/distributions/php-8.5.0.tar.gz
tar xzf php-8.5.0.tar.gz && cd php-8.5.0
./configure --prefix=/usr/local/php85 --enable-fpm --with-fpm-user=www-data \
    --with-fpm-group=www-data --with-mysqli --with-pdo-mysql --with-zip \
    --with-curl --with-openssl --with-mbstring --enable-intl --with-gd \
    --enable-opcache --with-zlib
make -j"$(nproc)" && make install
ln -s /usr/local/php85/bin/php /usr/local/bin/php

# сервис php-fpm
cp /usr/local/php85/etc/php-fpm.conf.default /usr/local/php85/etc/php-fpm.conf
cp /usr/local/php85/etc/php-fpm.d/www.conf.default /usr/local/php85/etc/php-fpm.d/www.conf
# ... оформите unit для systemd по образцу php8.5-fpm
```

## 2. База данных

```bash
mysql -e "CREATE DATABASE site CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'site'@'localhost' IDENTIFIED BY 'CHANGE_ME';"
mysql -e "GRANT ALL PRIVILEGES ON site.* TO 'site'@'localhost'; FLUSH PRIVILEGES;"
```

## 3. Код

```bash
mkdir -p /var/www/site && cd /var/www/site
git clone -b main https://github.com/DCoD-85/cms-damoony.ru.git .
composer install --no-dev --no-interaction
```

## 4. Nginx

```nginx
server {
    listen 80;
    server_name site.ru;
    root /var/www/site/www;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location /content/ { try_files $uri $uri/ /content.php?$query_string; }
    location = /submit { rewrite ^ /forms.php last; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
```

## 5. Конфиг и установка

```bash
# config/engine.config — доступ к БД вручную или из шаблона
php bin/cli.php cli:init --name=deploy
php bin/cli.php new --domain=site.ru --assembly=showcase
php bin/cli.php db:migrate --force
php bin/cli.php health:check
php bin/cli.php structure:check
```

Права:

```bash
mkdir -p content var
chown -R www-data:www-data app content var www/media www
chown root:www-data config/*.config && chmod 640 config/*.config
```

## 6. Проверка

- `https://site.ru/` → 301 на `/ru/`
- `/ru/`, `/en/` → 200
- `/ru/login/`, `/ru/registration/`, `/ru/policy/` → 200
- `api.site.ru/health` → JSON (если алиас `api` включён)

## 7. Обслуживание

```bash
crontab -e
*/15 * * * * cd /var/www/site && php bin/cli.php cron:run
5 8 * * *   cd /var/www/site && php bin/cli.php report:daily --alert
```

Бэкапы: `php bin/cli.php backup:create --keep=7`.
Наблюдаемость: `_doc/observability.md`, метрики — `/metrics.php?token=…`.
