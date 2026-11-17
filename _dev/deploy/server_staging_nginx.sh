#!/usr/bin/env bash
set -euo pipefail

STG=/var/www/staging.damoony.ru
cd "$STG"

echo "=== identity ownership ==="
chown root:root var/cli.identity
chmod 600 var/cli.identity
ls -l var/cli.identity

echo "=== health ==="
php bin/cli.php health:check || true

echo "=== domain list ==="
php bin/cli.php domain:list || true

echo "=== nginx vhost ==="
cat > /etc/nginx/sites-available/staging.damoony.ru <<'CONF'
server {
    listen 80;
    server_name staging.damoony.ru;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name staging.damoony.ru;
    root /var/www/staging.damoony.ru/www;
    index index.php index.html;

    ssl_certificate     /etc/ssl/damoony-wildcard/damoony.ru.crt;
    ssl_certificate_key /etc/ssl/damoony-wildcard/damoony.ru.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /content/ {
        try_files $uri $uri/ /content.php?$query_string;
    }

    location = /submit {
        rewrite ^ /forms.php last;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
CONF
ln -sf ../sites-available/staging.damoony.ru /etc/nginx/sites-enabled/staging.damoony.ru
nginx -t
systemctl reload nginx

echo "=== smoke via local resolve ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "root: %{http_code} -> %{redirect_url}\n" https://staging.damoony.ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "ru: %{http_code}\n" https://staging.damoony.ru/ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "en: %{http_code}\n" https://staging.damoony.ru/en/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "registration: %{http_code}\n" https://staging.damoony.ru/ru/registration/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "policy: %{http_code}\n" https://staging.damoony.ru/ru/policy/
