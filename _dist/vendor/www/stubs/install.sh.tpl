#!/bin/bash
# Damoony Engine Installer
set -e

DOMAIN="{{DOMAIN}}"
LICENSE_KEY="{{KEY}}"
VENDOR="https://damoony.ru"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

echo -e "${GREEN}=== Damoony Engine Installer ===${NC}"
echo "Домен: $DOMAIN"

# 1. Проверка зависимостей
echo -e "${YELLOW}[1/9] Проверка зависимостей...${NC}"
for cmd in php curl unzip mysql; do
    command -v "$cmd" >/dev/null 2>&1 || { echo -e "${RED}ОШИБКА: $cmd не найден${NC}"; exit 1; }
done

# 1.5. Проверка директории
echo -e "${YELLOW}[1.5/9] Проверка директории...${NC}"
SCRIPT_NAME=$(basename "${BASH_SOURCE[0]:-$0}")
if [ "$(ls -A | grep -v "^${SCRIPT_NAME}$" | wc -l)" -gt 0 ]; then
    echo -e "${RED}ОШИБКА: директория должна быть пустой (кроме install.sh)${NC}"
    exit 1
fi
echo "OK: директория чистая"

# 2. Настройки БД (Автоматическое создание)
echo ""
echo -e "${YELLOW}[2/9] Настройки БД${NC}"
DB_NAME="idoam_db"
DB_USER="idoam_user"
DB_PASS="StrongPass123"

DB_OK=0
for attempt in 1 2 3; do
    echo "--- Попытка $attempt/3 ---"
    read -p "MySQL Root (для создания БД) [root]: " MYSQL_ROOT
    MYSQL_ROOT=${MYSQL_ROOT:-root}
    read -sp "Пароль MySQL Root: " MYSQL_ROOT_PASS
    echo ""

    echo -e "${YELLOW}[3/9] Создание БД и пользователя...${NC}"
    # Дропаем старую базу и создаем новую
    if MYSQL_PWD="$MYSQL_ROOT_PASS" mysql -u "$MYSQL_ROOT" -e "
        DROP DATABASE IF EXISTS $DB_NAME; 
        CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
        GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
        FLUSH PRIVILEGES;
    " >/dev/null 2>&1; then
        echo -e "${GREEN}  ✓ База данных и пользователь созданы${NC}"
        DB_OK=1
        break
    fi
    echo -e "${RED}  ✗ Ошибка. Неверный пароль root или нет прав.${NC}"
done

if [ "$DB_OK" -ne 1 ]; then
    echo -e "${RED}ОШИБКА: не удалось настроить БД за 3 попытки.${NC}"
    exit 1
fi

# 4. Активация
echo -e "${YELLOW}[4/9] Активация лицензии...${NC}"
ACTIVATE=$(curl -sk -X POST "$VENDOR/api/activate.php" -H "Content-Type: application/json" -d "{\"key\":\"$LICENSE_KEY\",\"domain\":\"$DOMAIN\"}")
if ! echo "$ACTIVATE" | grep -q '"ok":true'; then
    echo -e "${RED}ОШИБКА активации: $ACTIVATE${NC}"
    exit 1
fi
ZIP_URL=$(echo "$ACTIVATE" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["download_url"] ?? "";')

# 5. Скачивание и распаковка
echo -e "${YELLOW}[5/9] Скачивание ядра...${NC}"
TMP=$(mktemp -d)
curl -sk "$ZIP_URL" -o "$TMP/core.zip"
unzip -q "$TMP/core.zip" -d "$(pwd)"
rm -rf "$TMP"

# 6. Конфиги
echo -e "${YELLOW}[6/9] Создание конфигов...${NC}"
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

# 7. Миграции
echo -e "${YELLOW}[7/9] Миграции БД...${NC}"
php bin/cli.php db:migrate

# 8. Сборка проекта и создание админа
echo -e "${YELLOW}[8/9] Сборка проекта (developer)...${NC}"
rm -f config/site.config
php bin/cli.php new --domain="$DOMAIN" --assembly=developer
php bin/dbcli.php db:schema:generate-provider --domain="$DOMAIN" --table=test_items

# Генерация пароля
ADMIN_LOGIN="admin"
ADMIN_PASS=$(php -r "echo substr(bin2hex(random_bytes(6)), 0, 12);")

# Создаем через CLI
php bin/cli.php user:new --login="$ADMIN_LOGIN" --email="admin@$DOMAIN" --password="$ADMIN_PASS" --role=admin --update

# Сохраняем в файл
cat > CREDENTIALS.txt << CRED
==========================================
 Администратор Damoony
==========================================

Логин:  $ADMIN_LOGIN
Пароль: $ADMIN_PASS
Email:  admin@$DOMAIN
Вход: https://$DOMAIN/ru/login/
CRED
chmod 600 CREDENTIALS.txt

# 9. Финализация
echo -e "${YELLOW}[9/9] Финализация...${NC}"
chown -R www-data:www-data "$(pwd)" || true
chmod -R 775 var www/media 2>/dev/null || true

cat > INSTALL_INSTRUCTION.txt << TXT
Установка завершена!
Домен: $DOMAIN
Инструкция: откройте https://$DOMAIN/ru/ или CREDENTIALS.txt
TXT

echo -e "${GREEN}======================================"
echo " УСТАНОВКА ЗАВЕРШЕНА"
echo -e "======================================${NC}"
echo "Логин: $ADMIN_LOGIN"
echo "Пароль: $ADMIN_PASS (сохранен в CREDENTIALS.txt)"