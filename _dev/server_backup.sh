#!/usr/bin/env bash
set -euo pipefail

STAMP=$(date +%Y-%m-%d_%H-%M-%S)
DIR="/root/backups/prod-before-refactor-$STAMP"
mkdir -p "$DIR"

echo "=== backup repo (with .git, without vendor) ==="
cd /var/www/damoony.ru
tar czf "$DIR/repo.tar.gz" --exclude=./vendor ./
ls -lh "$DIR/repo.tar.gz"

echo "=== backup databases ==="
mysqldump --single-transaction --routines --events --all-databases > "$DIR/all-databases.sql"
gzip -f "$DIR/all-databases.sql"
ls -lh "$DIR/all-databases.sql.gz"

echo "=== backup nginx + php-fpm configs ==="
tar czf "$DIR/nginx.tar.gz" /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/nginx/nginx.conf /etc/php/8.5/fpm 2>/dev/null || true
ls -lh "$DIR/nginx.tar.gz"

echo "=== git tag ==="
git tag -f prod-before-refactor "$(git rev-parse HEAD)"
git tag -l 'prod-*'

echo "=== github reachability from server ==="
timeout 20 git ls-remote --heads https://github.com/DCoD-85/cms-damoony.ru.git 2>&1 | head -5 || echo "github: unreachable"

echo "=== existing tables in www_damoony ==="
mysql -e "USE www_damoony; SHOW TABLES;" 2>&1 | head -10

echo "=== backup dir ==="
ls -lh "$DIR"
