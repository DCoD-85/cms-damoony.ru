#!/usr/bin/env bash
set -euo pipefail
STG=/var/www/staging.damoony.ru

echo "=== noindex header ==="
python3 - <<'PY'
import re
p = '/etc/nginx/sites-available/staging.damoony.ru'
s = open(p).read()
if 'X-Robots-Tag' not in s:
    s = s.replace('    index index.php index.html;\n', '    index index.php index.html;\n    add_header X-Robots-Tag "noindex, nofollow" always;\n')
    open(p, 'w').write(s)
    print('header added')
else:
    print('already present')
PY
nginx -t && systemctl reload nginx

echo "=== robots.txt ==="
cat > "$STG/www/robots.txt" <<'TXT'
User-agent: *
Disallow: /
TXT
chown www-data:www-data "$STG/www/robots.txt"

echo "=== backup:create ==="
cd "$STG"
php bin/cli.php backup:create --domain=damoony.ru 2>&1 | tail -8
ls -lh var/backups/ 2>/dev/null | head -5

echo "=== cron ==="
( crontab -l 2>/dev/null | grep -v 'staging.damoony.ru' ; \
  echo "*/15 * * * * cd /var/www/staging.damoony.ru && /usr/bin/php bin/cli.php cron:run >> /var/log/staging-cron.log 2>&1" ; \
  echo "5 8 * * * cd /var/www/staging.damoony.ru && /usr/bin/php bin/cli.php report:daily --alert >> /var/log/staging-report.log 2>&1" ) | crontab -
crontab -l | tail -3

echo "=== final http ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "ru:%{http_code}\n" https://staging.damoony.ru/ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -D - -o /dev/null https://staging.damoony.ru/ru/ | grep -i "x-robots" || true
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 https://staging.damoony.ru/robots.txt
