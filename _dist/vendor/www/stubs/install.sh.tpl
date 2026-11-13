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
echo -e "${YELLOW}[1/9] Проверка зависимостей...${NC}"
for cmd in php curl unzip mysql; do
    command -v "$cmd" >/dev/null 2>&1 || { echo -e "${RED}ОШИБКА: $cmd не найден${NC}"; exit 1; }
done
echo "PHP: $(php -r 'echo PHP_VERSION;')"

# 1.5. Проверка директории (БЕЗ УДАЛЕНИЯ!)
echo -e "${YELLOW}[1.5/9] Проверка директории...${NC}"
SCRIPT_NAME=$(basename "$0")
FILES_COUNT=$(ls -A | grep -v "^${SCRIPT_NAME}$" | wc -l)

if [ "$FILES_COUNT" -gt 0 ]; then
    echo -e "${RED}ОШИБКА: директория должна быть пустой (кроме install.sh)${NC}"
    echo "Найдено посторонних файлов: $FILES_COUNT"
    echo ""
    echo "Этот скрипт не удаляет существующие файлы."
    echo "Если нужна чистая установка, очистите папку вручную:"
    echo "  rm -rf $(pwd)/*"
    exit 1
fi
echo "OK: директория чистая"

# 2. Настройки БД (3 попытки, безопасный пароль)
echo ""
echo -e "${YELLOW}[2/9] Настройки БД${NC}"

DB_NAME="${DB_NAME:-idoam_db}"
DB_USER="${DB_USER:-idoam_user}"
DB_PASS=""
DB_OK=0

for attempt in 1 2 3; do
    echo "--- Попытка $attempt/3 ---"
    read -p "Имя БД [$DB_NAME]: " input_db_name
    DB_NAME=${input_db_name:-$DB_NAME}
    read -p "Пользователь БД [$DB_USER]: " input_db_user
    DB_USER=${input_db_user:-$DB_USER}
    read -sp "Пароль БД: " DB_PASS
    echo ""

    echo -e "${YELLOW}[3/9] Проверка подключения к БД...${NC}"
    echo "  DB: $DB_NAME | User: $DB_USER | Pass: ${#DB_PASS} символов"

    if MYSQL_PWD="$DB_PASS" mysql -u "$DB_USER" "$DB_NAME" -e "SELECT 1" >/dev/null 2>&1; then
        echo -e "${GREEN}  ✓ Подключение OK${NC}"
        DB_OK=1
        break
    fi
    echo -e "${RED}  ✗ Не удалось подключиться${NC}"
    echo ""
done

if [ "$DB_OK" -ne 1 ]; then
    echo -e "${RED}ОШИБКА: 3 неудачные попытки.${NC}"
    echo "Создайте пользователя вручную:"
    echo "  mysql -u root -e \"CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY 'ПАРОЛЬ';\""
    echo "  mysql -u root -e \"GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;\""
    exit 1
fi

# 4. Активация лицензии
echo -e "${YELLOW}[4/9] Активация лицензии...${NC}"
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
echo -e "${YELLOW}[5/9] Скачивание ядра...${NC}"
TMP=$(mktemp -d)
curl -sk "$ZIP_URL" -o "$TMP/core.zip"
unzip -q "$TMP/core.zip" -d "$(pwd)"
rm -rf "$TMP"
echo "OK"

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
echo "OK"

# 7. Миграции ДО сборки (чтобы users.role существовал)
echo -e "${YELLOW}[7/9] Миграции БД...${NC}"
php bin/cli.php db:migrate
echo "OK"

# 8. Сборка проекта и провайдеры
echo -e "${YELLOW}[8/9] Сборка проекта (developer assembly)...${NC}"
rm -f config/site.config
php bin/cli.php new --domain="$DOMAIN" --assembly=developer
php bin/dbcli.php db:schema:generate-provider --domain="$DOMAIN" --table=test_items

echo -e "${YELLOW}[8/9] Создание администратора...${NC}"
read -p "Логин админа [admin]: " ADMIN_LOGIN
ADMIN_LOGIN=${ADMIN_LOGIN:-admin}
read -sp "Пароль админа [admin123]: " ADMIN_PASS
ADMIN_PASS=${ADMIN_PASS:-admin123}
echo ""

# Гарантируем колонку role (на случай старых БД)
MYSQL_PWD="$DB_PASS" mysql -u "$DB_USER" "$DB_NAME" -e "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(16) NOT NULL DEFAULT 'user';" 2>/dev/null || true

# Генерируем хеш пароля через PHP
HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);")

# Создаем или обновляем админа напрямую через SQL (без ошибок при дублях)
MYSQL_PWD="$DB_PASS" mysql -u "$DB_USER" "$DB_NAME" -e "
INSERT INTO users (login, email, password_hash, role, status) 
VALUES ('$ADMIN_LOGIN', 'admin@$DOMAIN', '$HASH', 'admin', 'active')
ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash), 
    role = 'admin';
"

# 9. Права и инструкция
echo -e "${YELLOW}[9/9] Финализация...${NC}"
chown -R www-data:www-data "$(pwd)" || true
chmod -R 775 var www/media 2>/dev/null || true

cat > INSTALL_INSTRUCTION.txt << TXT
==========================================
 Damoony Engine - установка завершена
==========================================

Домен: $DOMAIN

Что делать дальше:
1. Откройте https://$DOMAIN/ru/ - главная
2. Войдите: https://$DOMAIN/ru/login/
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