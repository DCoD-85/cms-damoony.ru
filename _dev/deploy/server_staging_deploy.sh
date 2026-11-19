#!/usr/bin/env bash
set -euo pipefail

STG=/var/www/staging.damoony.ru
echo "=== clone branch ==="
rm -rf "$STG"
git clone -q -b refactor/site-config-v2 https://github.com/DCoD-85/cms-damoony.ru.git "$STG"
cd "$STG"
git log --oneline -1

echo "=== composer install ==="
composer install --no-dev --no-interaction --no-progress -q 2>&1 | tail -5 || true
ls vendor/autoload.php >/dev/null && echo "vendor ok"

echo "=== staging database ==="
mysql -e "DROP DATABASE IF EXISTS staging_damoony; CREATE DATABASE staging_damoony CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysqldump --single-transaction www_damoony 2>/dev/null | mysql staging_damoony
mysql -e "USE staging_damoony; SHOW TABLES;"

echo "=== engine.config ==="
cat > config/engine.config <<'JSON'
{
    "db": {
        "driver": "mysql",
        "host": "127.0.0.1",
        "dbname": "staging_damoony",
        "user": "root",
        "password": ""
    },
    "security": {
        "allow_php_blocks": false
    }
}
JSON

echo "=== cli:init ==="
php bin/cli.php cli:init --name=deploy

echo "=== new --assembly=minimal ==="
php bin/cli.php new --domain=damoony.ru --assembly=minimal

echo "=== bind staging mirror ==="
php bin/cli.php domain:bind --domain=staging.damoony.ru --real=damoony.ru

echo "=== permissions ==="
chown -R www-data:www-data app www/media var content www 2>/dev/null || true
chown root:www-data config/engine.config config/site.config
chmod 640 config/engine.config
chmod 660 config/site.config

echo "=== health ==="
php bin/cli.php health:check || true

echo "=== domain list ==="
php bin/cli.php domain:list || true
