#!/bin/bash
# Деплой vendor-файлов на сервер damoony.ru (запускать на сервере из корня проекта)
set -e

cd /var/www/damoony.ru

# Копируем vendor-файлы из _dist/
cp _dist/vendor/www/request.php ./www/request.php
mkdir -p ./www/api
cp _dist/vendor-api/activate.php ./www/api/activate.php
mkdir -p ./www/stubs
cp _dist/vendor/www/stubs/install.sh.tpl ./www/stubs/install.sh.tpl

# Папка с лицензиями
mkdir -p ./data
chown -R www-data:www-data ./www/request.php ./www/api ./www/stubs ./data

echo "OK: Vendor deployed."
echo "Откройте https://damoony.ru/request.php"
