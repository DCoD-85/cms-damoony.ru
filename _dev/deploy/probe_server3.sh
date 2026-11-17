#!/usr/bin/env bash
echo "=== vhost damoony.ru ==="
cat /etc/nginx/sites-available/damoony.ru 2>/dev/null
echo "=== vhost idoam.ru ==="
cat /etc/nginx/sites-available/idoam.ru 2>/dev/null
echo "=== default ==="
cat /etc/nginx/sites-available/default 2>/dev/null | head -30
echo "=== /var/www/idoam.ru ==="
ls -la /var/www/idoam.ru 2>/dev/null | head -20
echo "=== php.ini for fpm 8.5 ==="
php -i 2>/dev/null | grep -E "Loaded Configuration|error_log|upload_max" | head -5
echo "=== opcache ==="
php -i 2>/dev/null | grep -E "opcache.enable|opcache.validate" | head -5
