#!/usr/bin/env bash
set -euo pipefail

cd /var/www/damoony.ru

echo "=== git pull branch ==="
git fetch -q origin refactor/site-config-v2
git checkout -B refactor/site-config-v2 origin/refactor/site-config-v2
git log --oneline -1
git status --short | head -5

echo "=== composer ==="
composer install --no-dev --no-interaction --no-progress -q 2>&1 | tail -3 || true

echo "=== prod db user ==="
DBPASS=$(openssl rand -hex 16)
mysql <<SQL
CREATE USER IF NOT EXISTS 'damoony_prod'@'localhost' IDENTIFIED BY '${DBPASS}';
ALTER USER 'damoony_prod'@'localhost' IDENTIFIED BY '${DBPASS}';
GRANT ALL PRIVILEGES ON www_damoony.* TO 'damoony_prod'@'localhost';
FLUSH PRIVILEGES;
SQL

php -r '
$config = [
    "db" => ["driver" => "mysql", "host" => "127.0.0.1", "dbname" => "www_damoony", "user" => "damoony_prod", "password" => $argv[1]],
    "security" => ["allow_php_blocks" => false],
];
file_put_contents("config/engine.config", json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$DBPASS"

echo "=== cli:init ==="
php bin/cli.php cli:init --name=deploy

echo "=== new --force --assembly=minimal ==="
php bin/cli.php new --domain=damoony.ru --assembly=minimal --force 2>&1 | tail -6

echo "=== migrations ==="
php bin/cli.php db:migrate --force 2>&1 | tail -10

echo "=== permissions ==="
mkdir -p content
chown -R www-data:www-data app www/media var content www
chown root:www-data config/engine.config config/site.config
chmod 640 config/engine.config
chmod 660 config/site.config
chown root:root var/cli.identity
chmod 600 var/cli.identity

echo "=== nginx routes ==="
python3 - <<'PY'
p = '/etc/nginx/sites-available/damoony.ru'
s = open(p).read()
if '/content.php' not in s:
    s = s.replace('''    location ~ \\.php$ {''', '''    location /content/ {
        try_files $uri $uri/ /content.php?$query_string;
    }

    location = /submit {
        rewrite ^ /forms.php last;
    }

    location ~ \\.php$ {''')
    open(p, 'w').write(s)
    print('routes added')
else:
    print('routes already present')
PY
nginx -t
systemctl reload nginx

echo "=== health ==="
php bin/cli.php health:check 2>&1 | tail -10 || true
php bin/cli.php structure:check 2>&1 | tail -6 || true

echo "=== admin password ==="
ADMPASS=$(openssl rand -hex 12)
HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMPASS")
mysql -e "UPDATE www_damoony.users SET password_hash='${HASH}', email='admin@damoony.ru' WHERE login='admin';"
echo "$ADMPASS" > /root/prod-admin-password.txt
chmod 600 /root/prod-admin-password.txt
echo "admin password saved to /root/prod-admin-password.txt"

echo "=== http smoke ==="
curl -sk https://damoony.ru/ -o /dev/null -w "root:%{http_code}\n"
curl -sk https://damoony.ru/ru/ -o /dev/null -w "ru:%{http_code}\n"
curl -sk https://damoony.ru/en/ -o /dev/null -w "en:%{http_code}\n"
curl -sk https://damoony.ru/ru/registration/ -o /dev/null -w "registration:%{http_code}\n"
curl -sk https://damoony.ru/ru/policy/ -o /dev/null -w "policy:%{http_code}\n"
