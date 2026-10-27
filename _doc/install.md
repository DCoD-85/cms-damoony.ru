--- НАЧАЛО ФАЙЛА _doc/install.md ---

Установка и запуск

ТРЕБОВАНИЯ

PHP >= 8.4 (расширения: mbstring, json, pdo_mysql, openssl, fileinfo, tokenizer, xml, curl)

Composer

MariaDB 11+ (или MySQL 8+)

Apache 2.4 + mod_rewrite или Nginx + PHP-FPM

УСТАНОВКА
git clone <repo-url> damoony
cd damoony
composer install

КОНФИГУРАЦИЯ

config/engine.config
Главный конфиг движка (БД, лимиты, CSP).
Важно: Этот файл добавлен в .gitignore и не коммитится, чтобы у каждого сервера были свои доступы.

{
"rate_limit": {
"max": 120,
"window": 60,
"ban": 900
},
"csp": "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'",
"db": {
"driver": "mysql",
"host": "127.0.0.1",
"dbname": "your_database_name",
"user": "your_user",
"password": "your_password"
}
}

config/site.config
Основной конфиг доменов и алиасов. Включает маппинг входящих хостов и их доступность (также не коммитится).

{
"language": "ru",
"domains_real": {
"damoony.ru": "damoony.ru",
"damoony3.ru": "damoony.ru"
},
"domains_available": {
"damoony.ru": true
},
"domains_namespace": {
"damoony.ru": "damoony_ru"
},
"alliases_real": {
"damoony.ru": {
"www": "www",
"doc": "doc"
}
},
"alliases_available": {
"damoony.ru": {
"www": true,
"doc": true
}
},
"alliases_langs": {
"damoony.ru": {
"www": ["ru", "en", "zh"]
}
}
}

config/admin.config
Настройки доступа в админку (логин, хэш пароля) и список разрешенных языков.

{
"login": "admin",
"password_hash": "",
"domains_langs": ["ru", "en", "zh"],
"default_lang": "ru"
}

БАЗА ДАННЫХ
Создайте базу данных в MariaDB (через phpMyAdmin, HeidiSQL или консоль):
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

Примените миграции:
php bin/cli.php db:migrate

ВЕБ-СЕРВЕР

Apache
Убедитесь, что www/.htaccess используется и mod_rewrite включён. DocumentRoot должен указывать на папку www/.

Nginx
server {
listen 80;
server_name damoony.ru;
root /path/to/damoony/www;
index index.php;

location / {
try_files 
u
r
i
uriuri/ /index.php?$query_string;
}

location /admin/ {
try_files 
u
r
i
uriuri/ /admin/index.php?$query_string;
}

location ~ .phpdocument_root$fastcgi_script_name;
}
}

ПЕРВЫЙ ЗАПУСК

composer install

php bin/cli.php db:migrate

php bin/cli.php admin:passwd --name=admin --password=secret

php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret

Откройте http://ваш-домен/ (сайт) и http://ваш-домен/admin/ (админка).
(Команда user:new создаёт пользователя в БД, необходимого для входа на страницы с флагом "auth": true).

РАЗРАБОТКА И ТЕСТИРОВАНИЕ
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php

БЕЗОПАСНОСТЬ

Пароли — password_hash / password_verify (bcrypt).

CSRF — на всех POST-формах (в админке и на сайте).

Session — HttpOnly, SameSite=Lax, Secure при HTTPS.

Brute-force — бан IP на 15 минут после 5 неудач.

CSP — блокирует inline-скрипты.

Файлы в www/media/ — запрещены .php скрипты.

--- КОНЕЦ ФАЙЛА _doc/install.md ---