#!/bin/bash
# Damoony Engine Installer
set -e

DOMAIN="{{DOMAIN}}"
LICENSE_KEY="{{KEY}}"
VENDOR="https://damoony.ru"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

echo -e "${GREEN}=== Damoony Engine Installer ===${NC}"
echo "Домен: $DOMAIN"
echo "Ключ: $LICENSE_KEY"
echo ""

# 1. Проверка зависимостей
echo -e "${YELLOW}[1/8] Проверка зависимостей...${NC}"
for cmd in php curl unzip mysql; do
    command -v "$cmd" >/dev/null 2>&1 || { echo -e "${RED}ОШИБКА: $cmd не найден${NC}"; exit 1; }
done
PHP_VER=$(php -r 'echo PHP_VERSION;')
echo "PHP: $PHP_VER"

# 2. Запрос БД
echo ""
echo -e "${YELLOW}[2/8] Настройки БД${NC}"
read -p "Имя БД [idoam_db]: " DB_NAME
DB_NAME=${DB_NAME:-idoam_db}
read -p "Пользователь БД [idoam_user]: " DB_USER
DB_USER=${DB_USER:-idoam_user}
read -sp "Пароль БД: " DB_PASS
echo ""

# 3. Проверка БД
echo -e "${YELLOW}[3/8] Проверка БД...${NC}"
if ! mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" >/dev/null 2>&1; then
    echo -e "${RED}ОШИБКА: не могу подключиться к БД${NC}"
    exit 1
fi
echo "OK"

# 4. Активация
echo -e "${YELLOW}[4/8] Активация лицензии...${NC}"
ACTIVATE=$(curl -sk -X POST "$VENDOR/api/activate.php" \
    -H "Content-Type: application/json" \
    -d "{\"key\":\"$LICENSE_KEY\",\"domain\":\"$DOMAIN\"}")

if ! echo "$ACTIVATE" | grep -q '"ok":true'; then
    echo -e "${RED}ОШИБКА активации: $ACTIVATE${NC}"
    exit 1
fi

ZIP_URL=$(echo "$ACTIVATE" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["download_url"] ?? "";')
echo "Скачиваю: $ZIP_URL"

# 5. Скачивание и распаковка
echo -e "${YELLOW}[5/8] Скачивание ядра...${NC}"
TMP=$(mktemp -d)
curl -sk "$ZIP_URL" -o "$TMP/core.zip"
unzip -q "$TMP/core.zip" -d "$(pwd)"
rm -rf "$TMP"
echo "OK"

# 6. Конфиги
echo -e "${YELLOW}[6/8] Создание конфигов...${NC}"
mkdir -p config
cat > config/engine.config << JSON
{
    "db": {
        "driver": "mysql",
        "host": "localhost",
        "dbname": "$DB_NAME",
        "user": "$DB_USER",
        "password": "$DB_PASS"
    },
    "security": {
        "allow_php_blocks": false
    }
}
JSON
echo "OK"

# 7. Сборка
echo -e "${YELLOW}[7/8] Сборка проекта (developer assembly)...${NC}"
rm -f config/site.config
php bin/cli.php new --domain="$DOMAIN" --assembly=developer
php bin/cli.php db:migrate
php bin/dbcli.php db:schema:generate-provider --domain="$DOMAIN" --table=test_items

echo -e "${YELLOW}[7/8] Создание администратора...${NC}"
read -p "Логин админа [admin]: " ADMIN_LOGIN
ADMIN_LOGIN=${ADMIN_LOGIN:-admin}
read -sp "Пароль админа [admin123]: " ADMIN_PASS
ADMIN_PASS=${ADMIN_PASS:-admin123}
echo ""
php bin/cli.php user:new --login="$ADMIN_LOGIN" --email="admin@$DOMAIN" --password="$ADMIN_PASS"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE users SET role='admin' WHERE login='$ADMIN_LOGIN';"

# 8. Права и инструкция
echo -e "${YELLOW}[8/8] Финализация...${NC}"
chown -R www-data:www-data "$(pwd)" || true
chmod -R 775 var www/media 2>/dev/null || true

cat > INSTALL_INSTRUCTION.txt << TXT
==========================================
 Damoony Engine - установка завершена
==========================================

Домен: $DOMAIN

Что делать дальше:
1. Откройте https://$DOMAIN/ru/ - главная страница
2. Войдите в админку: https://$DOMAIN/ru/login/
3. Логин/пароль администратора: см. выше

Управление:
- CLI: php bin/cli.php help
- Конфиги: config/
- Логи: var/

Скины: https://$DOMAIN/ru/skin/dark/
Языки: https://$DOMAIN/en/

Поддержка: support@damoony.ru
TXT

echo ""
echo -e "${GREEN}======================================"
echo " УСТАНОВКА ЗАВЕРШЕНА"
echo -e "======================================${NC}"
echo "Админ: $ADMIN_LOGIN / $ADMIN_PASS"
echo "Откройте: https://$DOMAIN/ru/"
echo "Инструкция: INSTALL_INSTRUCTION.txt"