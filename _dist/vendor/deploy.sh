#!/bin/bash
set -e

# Определяем корень проекта (поднимаемся на 2 уровня вверх от _dist/vendor)
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "Деплой вендора в $ROOT_DIR"

# Копируем vendor-файлы из _dist/
cp _dist/vendor/www/request.php ./www/request.php
mkdir -p ./www/api
cp _dist/vendor-api/activate.php ./www/api/activate.php
mkdir -p ./www/stubs
cp _dist/vendor/www/stubs/install.sh.tpl ./www/stubs/install.sh.tpl

# Папка с лицензиями
mkdir -p ./data
if [ ! -f ./data/licenses.json ]; then
    echo "[]" > ./data/licenses.json
fi
if [ ! -f ./data/activations.log ]; then
    touch ./data/activations.log
fi

# Права доступа (если запущено от root)
chown -R www-data:www-data ./www/request.php ./www/api ./www/stubs ./data 2>/dev/null || true
chmod -R 775 ./data 2>/dev/null || true
chmod 664 ./data/licenses.json ./data/activations.log 2>/dev/null || true

echo "OK: Vendor deployed."
echo "Откройте https://damoony.ru/request.php"
