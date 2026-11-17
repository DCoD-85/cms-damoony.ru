#!/usr/bin/env bash
echo "=== cert SAN ==="
openssl x509 -in /etc/ssl/damoony-wildcard/damoony.ru.crt -noout -subject -ext subjectAltName 2>&1 | head -6
echo "=== composer ==="
command -v composer || true
php /var/www/damoony.ru/composer.phar --version 2>&1 | head -1
echo "=== composer.json php req ==="
grep -A3 '"require"' /var/www/damoony.ru/composer.json | head -6
echo "=== dns staging ==="
getent hosts staging.damoony.ru || echo "no dns for staging.damoony.ru"
echo "=== dns damoony ==="
getent hosts damoony.ru | head -2
echo "=== mysql root ==="
mysql -e "SELECT VERSION();" 2>&1 | head -2
