#!/usr/bin/env bash
set -euo pipefail

STG=/var/www/staging.damoony.ru
cd "$STG"

echo "=== remove demo project ==="
php bin/cli.php domain:remove --domain=damoony.ru --force 2>&1 | tail -3 || true
rm -rf app/damoony.ru www/media/damoony.ru app/damoony.ru_trash_* www/media/.trash/* 2>/dev/null || true

echo "=== new minimal ==="
php bin/cli.php new --domain=damoony.ru --assembly=minimal 2>&1 | tail -4

echo "=== bind staging ==="
php bin/cli.php domain:bind --domain=staging.damoony.ru --real=damoony.ru 2>&1 | tail -3

echo "=== permissions ==="
chown -R www-data:www-data app www/media var content www 2>/dev/null || true
chown root:www-data config/engine.config config/site.config
chmod 640 config/engine.config config/site.config
chown root:root var/cli.identity
chmod 600 var/cli.identity

echo "=== structure ==="
php bin/cli.php structure:check 2>&1 | tail -12 || true

echo "=== http ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "root:%{http_code}\n" https://staging.damoony.ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "ru:%{http_code}\n" https://staging.damoony.ru/ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /tmp/stg.html -w "html:%{http_code}\n" https://staging.damoony.ru/ru/
echo "--- markers ---"
for m in dt_top lang-switcher skin-switcher registration cookie-banner; do
  if grep -q "$m" /tmp/stg.html; then echo "$m: yes"; else echo "$m: NO"; fi
done
grep -o 'href="/ru/[a-z]*/"' /tmp/stg.html | sort -u | head -10
