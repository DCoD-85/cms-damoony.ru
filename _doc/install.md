--- НАЧАЛО ДОКУМЕНТА ---

Установка и запуск
Документ описывает развёртывание Damoony Engine с нуля. Проект находится в состоянии 98% production-ready (0 P0-блокеров). Перед деплоем прочитайте MANIFEST.md.

Требования
PHP >= 8.4 (расширения: mbstring, json, pdo_mysql, openssl, fileinfo, tokenizer, xml, curl).

Composer.

MariaDB 11+ (или MySQL 8+). SQLite — для тестов, не для production.

Apache 2.4 + mod_rewrite или Nginx + PHP-FPM.

Установка
bash
git clone <repo-url> damoony
cd damoony
composer install

Конфигурация
config/engine.config — главный конфиг движка
БД, лимиты, CSP и настройки безопасности. Не коммитится (.gitignore).

json
{
  "rate_limit": {
    "max": 120,
    "window": 60,
    "ban": 900
  },
  "security": {
    "allow_php_blocks": false
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
⚠️ security.allow_php_blocks: По умолчанию false. Включайте (true) только если вам действительно нужны блоки с сырым PHP (custom/if). Это защита от RCE.

config/site.config — домены и алиасы
Не коммитится. Карта входящих хостов → реальных доменов.

json
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
⚠️ Обязательно: self-mapping в domains_real. Ключ реального домена должен указывать на себя ("damoony.ru": "damoony.ru"). Без этого CLI-команды упадут с Undefined array key.

config/admin.config — доступ в админку
Логин, хеш пароля, список разрешённых языков.

json
{
  "login": "admin",
  "password_hash": "",
  "domains_langs": ["ru", "en", "zh"],
  "default_lang": "ru"
}
⚠️ Пустой password_hash — админка недоступна. Задайте через php bin/cli.php admin:passwd --name=admin --password=<strong>.

База данных
Создайте базу:

sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
Примените миграции:

bash
php bin/cli.php db:migrate
Команда идемпотентна. Поддерживает MariaDB/MySQL и SQLite (тесты).

Веб-сервер
Apache
DocumentRoot → www/. Включите mod_rewrite. Убедитесь, что www/.htaccess используется (AllowOverride All для www/).

Nginx
nginx
server {
    listen 80;
    server_name damoony.ru;
    root /path/to/damoony/www;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /admin/ {
        try_files $uri $uri/ /admin/index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}

Первый запуск
bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=<strong>
php bin/cli.php user:new --login=admin --email=admin@example.com --password=<strong>

admin:passwd — администратор админки (/admin/).
user:new — пользователь сайта (для страниц с "auth": true). Это разные учётки. Не путайте.

Откройте http://<домен>/ (сайт) и http://<домен>/admin/ (админка).

Создание первого сайта
bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
Алиас www создаётся автоматически. Проверьте config/site.config.

⚠️ page:new создаёт страницу без схемы по умолчанию (MANIFEST.md §6.1). Шапки/футера на ней не будет. Для обычной страницы — guide.md §4, Вариант A (вызов SchemeRenderer в коде).

Развёртывание по IP (intranet, staging)
В config/site.config добавьте IP как ключ в domains_real (без порта):

json
"domains_real": {
    "192.168.1.10": "example.ru",
    "example.ru": "example.ru"
}
Откройте http://192.168.1.10/. 
⚠️ Порт и trailing-dot нормализуются автоматически (stage-3). Для нескольких алиасов нужны DNS/hosts (например damoony.local, doc.damoony.local → тот же IP).

Разработка и тестирование
bash
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php
Или через composer:

bash
composer test
composer stan
composer cs
composer check   # stan + cs + test

Безопасность (перед production)
Пароли — password_hash/password_verify (bcrypt). admin:passwd и user:new делают это.
CSRF — на всех POST (админка и сайт). Destructive операции защищены.
Сессии — HttpOnly, SameSite=Lax, Secure при HTTPS.
Brute-force — бан IP на 15 минут после 5 неудач.
CSP — блокирует inline-скрипты (CodeMirror локальный из /media/admin/).
Визуальные редакторы удалены для страниц и блоков (CodeMirror). Drag&Drop оставлен только для Схем.
Флаг security.allow_php_blocks=false по умолчанию блокирует генерацию сырого PHP.

Проверка после установки
bash
php bin/cli.php structure:check
✅ Проверяет наличие всех 3 файлов (.php, .rules.json, .editor.json) для каждого элемента, стыковку филлер↔слот. Выводит OK или список ошибок.
Если есть пропущенные файлы — восстановить можно командой:
bash
php bin/cli.php structure:repair --domain=example.ru

Полезные ссылки
MANIFEST.md — канон: ✅/🟡/❌, P0/P1/P2, backlog, видение.
readme.md — обзор и быстрый старт.
cli.md — CLI-команды.
admin.md — админка.
guide.md — руководство разработчика.
architecture.md — жизненный цикл (включая AI-слой).
ai_agent.md — интеграция AI-агентов.
contracts.md — контракты и ограничения.

--- КОНЕЦ ДОКУМЕНТА ---