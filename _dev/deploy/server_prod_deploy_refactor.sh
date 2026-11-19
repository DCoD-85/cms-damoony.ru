#!/usr/bin/env bash
# Прод-деплой рефакторинга Damoony.
# Запускать только после явного согласования и бэкапа.
set -euo pipefail

ROOT="/var/www/damoony.ru"
BRANCH="refactor/site-config-v2"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/root/backups/prod-refactor-${STAMP}"
PHP="php"

if [ "${1:-}" != "--yes" ]; then
  echo "Это прод-деплой ${ROOT} (${BRANCH})."
  echo "Запуск: bash $0 --yes"
  exit 1
fi

cd "${ROOT}"

echo "[1/8] backup -> ${BACKUP_DIR}"
mkdir -p "${BACKUP_DIR}"
tar czf "${BACKUP_DIR}/config-content-var.tar.gz" config content var 2>/dev/null || tar czf "${BACKUP_DIR}/config-content-var.tar.gz" config content
DB_CNF="$(mktemp)"
"${PHP}" -r '
$j = json_decode(file_get_contents("config/engine.config"), true)["db"] ?? [];
file_put_contents($argv[1], "[client]\nhost=" . ($j["host"] ?? "localhost") . "\nuser=" . ($j["user"] ?? "root") . "\npassword=" . ($j["password"] ?? "") . "\n");
' "${DB_CNF}"
DB_NAME="$("${PHP}" -r '$j = json_decode(file_get_contents("config/engine.config"), true)["db"] ?? []; echo $j["dbname"] ?? "";')"
mysqldump --defaults-extra-file="${DB_CNF}" --single-transaction "${DB_NAME}" | gzip > "${BACKUP_DIR}/db.sql.gz"
rm -f "${DB_CNF}"

echo "[2/8] git fetch/reset"
git fetch -q origin "${BRANCH}"
git reset -q --hard "origin/${BRANCH}"

echo "[3/8] composer"
if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --no-interaction --optimize-autoloader
else
  "${PHP}" composer.phar install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true
fi

echo "[4/8] migrations"
if [ ! -f var/cli.identity ]; then
  "${PHP}" bin/cli.php cli:init --name=deploy
fi
"${PHP}" bin/cli.php db:migrate --force

echo "[5/8] сборка showcase"
"${PHP}" bin/cli.php new --domain=damoony.ru --assembly=showcase --force

echo "[6/8] session_domain + права"
"${PHP}" -r '
$f = "config/engine.config";
$j = json_decode(file_get_contents($f), true);
$j["security"]["session_domain"] = ".damoony.ru";
file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
mkdir -p content
chown -R www-data:www-data app content var www 2>/dev/null || true
chown root:www-data config/engine.config config/site.config 2>/dev/null || true
chmod 640 config/engine.config
chmod 660 config/site.config 2>/dev/null || true
chown root:root var/cli.identity 2>/dev/null || true
chmod 600 var/cli.identity 2>/dev/null || true

echo "[7/8] nginx"
cp -a /etc/nginx/sites-available/damoony.ru "${BACKUP_DIR}/nginx-damoony.ru.conf" 2>/dev/null || true
install -m 644 _dev/deploy/nginx/prod-main.conf /etc/nginx/sites-available/damoony.ru
ln -sfn /etc/nginx/sites-available/damoony.ru /etc/nginx/sites-enabled/damoony.ru
for name in persona developer admin; do
  install -m 644 "_dev/deploy/nginx/prod-${name}.conf" "/etc/nginx/sites-available/${name}.damoony.ru"
  ln -sfn "/etc/nginx/sites-available/${name}.damoony.ru" "/etc/nginx/sites-enabled/${name}.damoony.ru"
done
nginx -t
systemctl reload nginx
systemctl reload php8.5-fpm

echo "[8/8] проверки"
"${PHP}" bin/cli.php structure:check
"${PHP}" bin/cli.php health:check
for host in damoony.ru persona.damoony.ru developer.damoony.ru admin.damoony.ru; do
  code="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "${host}:443:127.0.0.1" "https://${host}/ru/")"
  echo "  ${host}/ru/ -> ${code}"
done

echo "Готово. Бэкап: ${BACKUP_DIR}"
