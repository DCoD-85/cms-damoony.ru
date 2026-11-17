#!/usr/bin/env bash
set -euo pipefail

STG=/var/www/staging.damoony.ru
cd "$STG"

echo "=== pull ==="
git fetch -q origin refactor/site-config-v2
git reset -q --hard origin/refactor/site-config-v2
git log --oneline -1
composer install --no-dev --no-interaction --no-progress -q 2>&1 | tail -3 || true

echo "=== cli:init ==="
php bin/cli.php cli:init --name=deploy

echo "=== new --assembly=minimal ==="
php bin/cli.php new --domain=damoony.ru --assembly=minimal 2>&1 | tail -5

echo "=== bind staging mirror ==="
php bin/cli.php domain:bind --domain=staging.damoony.ru --real=damoony.ru 2>&1 | tail -5

echo "=== permissions ==="
chown -R www-data:www-data app www/media var content www 2>/dev/null || true
chown root:root config/engine.config config/site.config 2>/dev/null || true
chmod 640 config/engine.config config/site.config 2>/dev/null || true

echo "=== health ==="
php bin/cli.php health:check || true

echo "=== domain list ==="
php bin/cli.php domain:list || true
