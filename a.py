from pathlib import Path

BASE = Path.cwd()
tpl_path = BASE / "_dist/vendor/www/stubs/install.sh.tpl"

if not tpl_path.exists():
    print("FAIL: tpl file not found")
    exit(1)

src = tpl_path.read_text(encoding="utf-8")

# 1. Фикс basename
src = src.replace(
    'SCRIPT_NAME=$(basename "$0")',
    'SCRIPT_NAME=$(basename "${BASH_SOURCE[0]:-$0}")'
)

# 2. Замена блока с SQL на CLI-вызов
old_block = """# Гарантируем колонку role (на случай старых БД)
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
"""

new_block = """# Создаем или обновляем админа через CLI (CLI-First)
php bin/cli.php user:new --login="$ADMIN_LOGIN" --email="admin@$DOMAIN" --password="$ADMIN_PASS" --role=admin --update
"""

if new_block in src:
    print("SKIP: already patched")
elif old_block not in src:
    print("FAIL: marker not found")
else:
    src = src.replace(old_block, new_block, 1)
    tpl_path.write_text(src, encoding="utf-8", newline="")
    print("OK: install.sh.tpl patched (removed hardcode, fixed basename)")