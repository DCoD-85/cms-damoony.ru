#!/bin/bash
# Деплой vendor-файлов на сервер damoony.ru (запускать на сервере из корня проекта)
set -e

cd /var/www/damoony.ru/www

# Копируем vendor-файлы из _dist/vendor/www/
cp _dist/vendor/www/request.php ./request.php
cp _dist/vendor/www/api/activate.php ./api/activate.php
mkdir -p ./stubs
cp _dist/vendor/www/stubs/install.sh.tpl ./stubs/install.sh.tpl

# Папка с лицензиями
mkdir -p ./data
chown -R www-data:www-data ./request.php ./api ./stubs ./data

echo "OK: Vendor deployed."
echo "Откройте https://damoony.ru/request.php"