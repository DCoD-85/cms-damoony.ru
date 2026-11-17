#!/usr/bin/env bash
cd /var/www/damoony.ru || exit 1

echo "=== app tree ==="
find app -maxdepth 3 -type d | head -20
echo "--- counts ---"
find app -type f | wc -l
du -sh app 2>/dev/null

echo "=== nginx vhosts ==="
ls -la /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null
grep -rs "server_name" /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null | head -10
grep -rs "fastcgi_pass" /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null | head -5

echo "=== mysql databases ==="
mysql -e "SHOW DATABASES;" 2>&1 | head -15
mysql -e "SELECT table_schema, COUNT(*) AS tables FROM information_schema.tables WHERE table_schema NOT IN ('mysql','information_schema','performance_schema','sys') GROUP BY table_schema;" 2>&1 | head -10

echo "=== www/media ==="
du -sh www/media 2>/dev/null
find www/media -maxdepth 2 -type d | head -10

echo "=== content ==="
ls -la content 2>/dev/null | head -5
du -sh content 2>/dev/null
