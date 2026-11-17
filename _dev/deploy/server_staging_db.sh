#!/usr/bin/env bash
set -euo pipefail

STG=/var/www/staging.damoony.ru
cd "$STG"

DBPASS=$(openssl rand -hex 16)
mysql <<SQL
CREATE USER IF NOT EXISTS 'damoony_stg'@'localhost' IDENTIFIED BY '${DBPASS}';
ALTER USER 'damoony_stg'@'localhost' IDENTIFIED BY '${DBPASS}';
GRANT ALL PRIVILEGES ON staging_damoony.* TO 'damoony_stg'@'localhost';
FLUSH PRIVILEGES;
SQL

php -r '
$pass = $argv[1];
$config = [
    "db" => ["driver" => "mysql", "host" => "127.0.0.1", "dbname" => "staging_damoony", "user" => "damoony_stg", "password" => $pass],
    "security" => ["allow_php_blocks" => false],
];
file_put_contents("config/engine.config", json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "config written\n";
' "$DBPASS"

chown root:root config/engine.config
chmod 640 config/engine.config

echo "=== health ==="
php bin/cli.php health:check || true

echo "=== http ==="
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "root: %{http_code}\n" https://staging.damoony.ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "ru: %{http_code}\n" https://staging.damoony.ru/ru/
curl -sk --resolve staging.damoony.ru:443:127.0.0.1 -o /dev/null -w "registration: %{http_code}\n" https://staging.damoony.ru/ru/registration/

echo "=== error log tail ==="
tail -n 10 /var/log/nginx/error.log 2>/dev/null || true
journalctl -u php8.5-fpm --no-pager -n 10 2>/dev/null | tail -10 || true
