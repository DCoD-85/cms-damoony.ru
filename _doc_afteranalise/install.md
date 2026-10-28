--- НАЧАЛО ДОКУМЕНТА ---

Установка и запуск
⚠️ Требует проверки при запуске. Этот документ составлен по коду и конфигам, но не проверен реальным запуском db:migrate + composer install на чистой машине. Если что-то не сходится — истина в коде и в MANIFEST.md. Перед деплоем в production обязательно прочитайте MANIFEST.md §7 (6 P0-блокеров).

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
БД, лимиты, CSP. Не коммитится (.gitignore), у каждого сервера свои доступы.

json
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
⚠️ Обязательно: self-mapping в domains_real. Ключ реального домена должен указывать на себя ("damoony.ru": "damoony.ru"). Без этого PageController::save() и BlockController::save() упадут с Undefined array key (MANIFEST.md §6.4, P2).

⚠️ Порт и trailing-dot не нормализуются. ConfigDomainResolver не отрезает :8080 и . (MANIFEST.md §6.17, P0). Пишите хост без порта. Для развёртывания по IP с нестандартным портом — ждите фикса или используйте DNS/localhost.

config/admin.config — доступ в админку
Логин, хеш пароля, список разрешённых языков.

json
{
  "login": "admin",
  "password_hash": "",
  "domains_langs": ["ru", "en", "zh"],
  "default_lang": "ru"
}
⚠️ Пустой password_hash — админка недоступна. Задайте через php bin/cli.php admin:passwd --name=admin --password=<strong>. Не оставляйте дефолтный admin / secret — смените перед деплоем.

База данных
Создайте базу:

sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
Примените миграции:

bash
php bin/cli.php db:migrate
Команда идемпотентна — повторный запуск не дублирует миграции. Поддерживает MariaDB/MySQL и SQLite (тесты).

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
⚠️ Блок location ~ \.php$ в оригинальном install.md был обрезан. Приведён рабочий вариант. Если у вас другой пул PHP-FPM — поправьте fastcgi_pass.

Первый запуск
bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=<strong>
php bin/cli.php user:new --login=admin --email=admin@example.com --password=<strong>
admin:passwd — администратор админки (/admin/).

user:new — пользователь сайта (для страниц с "auth": true). Это разные учётки (MANIFEST.md §Auth). Не путайте.

Откройте http://<домен>/ (сайт) и http://<домен>/admin/ (админка).

Создание первого сайта
bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
Алиас www создаётся автоматически. Проверьте config/site.config — команда обновляет его.

⚠️ page:new создаёт страницу без схемы по умолчанию (MANIFEST.md §6.1). Шапки/футера на ней не будет. Для обычной страницы — guide.md §4, Вариант A.

Развёртывание по IP (intranet, staging)
В config/site.config добавьте IP как ключ в domains_real (без порта):

json
"domains_real": {
    "192.168.1.10": "example.ru",
    "example.ru": "example.ru"
}
Откройте http://192.168.1.10/.

⚠️ На один IP-хост без DNS работает только один алиас (www) — алиас определяется по Host. Для нескольких — DNS/hosts (например damoony.local, doc.damoony.local → тот же IP).

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

CSRF — на всех POST (админка и сайт). Destructive GET — баг (§7.1 п.1), не используйте.

Сессии — HttpOnly, SameSite=Lax, Secure при HTTPS.

Brute-force — бан IP на 15 минут после 5 неудач.

CSP — блокирует inline-скрипты (CodeMirror локальный из /media/admin/).

Файлы в www/media/ — запрещены .php-скрипты (whitelist загрузок в MediaController).

Проверка после установки
bash
php bin/cli.php structure:check
⚠️ structure:check не ловит .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки, schemes.json ↔ файлы (MANIFEST.md §7.2 п.3, P1). Это первый шаг, но не единственный. Дополнительно проверьте вручную.

6 P0-блокеров перед production (MANIFEST.md §7.1)
CSRF на destructive GET (SchemeController::delete/copy, BlockController::remove).

addslashes в компиляторах (ломает ', \, $, \n).

Response::internalError($e->getMessage()) — утечка путей/классов в prod.

Два формата .edit.json без миграции — потеря контента.

SchemeCompiler::str_replace('array (', '[') — ломается на скобках.

Порт в Host не нормализуется — IP:порт не работает.

До закрытия P0: не давайте админку непрофессионалам; визуальное сохранение — только после коммита в git; для обычных страниц используйте ручной PHP (Вариант A из guide.md §4).

Полезные ссылки
MANIFEST.md — канон: ✅/🟡/❌, P0/P1/P2, backlog, видение.

readme.md — обзор и быстрый старт.

cli.md — CLI-команды.

admin.md — админка.

guide.md — руководство разработчика.

architecture.md — жизненный цикл.

contracts.md — контракты и ограничения.

scenarios.md — Use Cases со статусами.

--- КОНЕЦ ДОКУМЕНТА ---