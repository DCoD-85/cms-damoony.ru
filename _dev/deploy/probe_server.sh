#!/usr/bin/env bash
cd /var/www/damoony.ru || exit 1

echo "=== top dirs ==="
ls -la app www stubs config 2>/dev/null | head -40

echo "=== git ==="
git rev-parse --abbrev-ref HEAD
git log --oneline -3
git status --short | head -5

echo "=== configs ==="
for f in config/site.config config/engine.config config/admin.config; do
  if [ -S "$f" ]; then echo "$f: (0 bytes)"; continue; fi
  echo "$f: $(wc -c < "$f" 2>/dev/null || echo missing) bytes"
done

echo "=== php modules ==="
php -m | paste -sd' ' -

echo "=== nginx roots ==="
grep -rs "root " /etc/nginx/sites-enabled/ | head -8

echo "=== nginx php sock ==="
grep -rs "fastcgi_pass" /etc/nginx/sites-enabled/ | head -4

echo "=== disk ==="
df -h /var/www | tail -1

echo "=== cron ==="
crontab -l 2>/dev/null | head -10

echo "=== php-fpm version ==="
ls /etc/php/ 2>/dev/null
systemctl list-units --type=service --state=running | grep -E 'php|nginx|mysql|maria' | head -6
