#!/usr/bin/env bash
set -euo pipefail
cd /var/www/staging.damoony.ru

echo "=== registration POST ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -c /tmp/stg.jar -o /tmp/reg.html -w "GET %{http_code}\n" https://staging.damoony.ru/ru/registration/
TOKEN=$(grep -o 'name="_csrf" value="[a-f0-9]*"' /tmp/reg.html | head -1 | sed 's/.*value="//;s/"//')
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -b /tmp/stg.jar -c /tmp/stg.jar -o /dev/null -D /tmp/reg_headers.txt -w "POST %{http_code}\n" -X POST \
  --data-urlencode "login=stgtest2" --data-urlencode "email=stg2@example.test" \
  --data-urlencode "password=password123" --data-urlencode "password2=password123" \
  --data-urlencode "_csrf=$TOKEN" https://staging.damoony.ru/ru/registration/
grep -i '^location' /tmp/reg_headers.txt || true

echo "=== profile ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -b /tmp/stg.jar https://staging.damoony.ru/ru/profile/ -o /tmp/prof.html -w "profile %{http_code}\n"
grep -o 'stgtest2' /tmp/prof.html | head -1 || echo "login not found"
grep -o 'href="/ru/logout/"' /tmp/prof.html | head -1 || true

echo "=== skin switch ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -c /tmp/skin.jar -o /dev/null -w "dark %{http_code}\n" https://staging.damoony.ru/ru/skin/dark/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -b /tmp/skin.jar https://staging.damoony.ru/ru/ -o /tmp/dark.html -w "home %{http_code}\n"
grep -o 'skin/dark.css\|data-skin="dark"\|/skin/dark/' /tmp/dark.html | head -3 || true

echo "=== cleanup test users ==="
mysql -e "DELETE FROM staging_damoony.users WHERE login IN ('stgtest','stgtest2');"
echo "=== structure/health ==="
php bin/cli.php structure:check 2>&1 | tail -6
php bin/cli.php health:check 2>&1 | tail -8
