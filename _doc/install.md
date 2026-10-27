--- НАЧАЛО ФАЙЛА _docrebuild/install.md ---

Установка и запуск
Требования
PHP >= 8.4 (расширения: mbstring, json, pdo_sqlite, pdo_mysql, openssl, fileinfo, tokenizer, xml, curl)

Composer

Apache 2.4 + mod_rewrite или Nginx + PHP-FPM

SQLite (для разработки) или MySQL/MariaDB (для продакшена)

Установка
bash
git clone <repo-url> damoony
cd damoony
composer install
Конфигурация
config/site.config
Основной конфиг доменов и алиасов. Включает инфраструктуру (доступность) и маппинг входящих хостов.

json
{
    "language": "ru-ru",
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
    "fillers": {
        "damoony.ru": {
            "doc": {
                "top": false
            }
        }
    },
    "domains_titles": {
        "damoony.ru": ""
    },
    "domains_stub": {
        "damoony.ru": {
            "current": "coming_soon"
        }
    },
    "domains_comments": {
        "damoony.ru": ""
    }
}
domains_real — карта: входящий домен → реальный домен (папка в app/).

domains_available — флаг доступности реального домена (false → 503 Maintenance).

domains_namespace — PHP-неймспейс для классов домена.

alliases_real — доступные алиасы для реального домена.

alliases_available — флаг доступности конкретного алиаса (false → 503 Maintenance для этого алиаса).

fillers — глобальные переключатели для филлеров (например, отключение top для doc).

domains_titles — заголовок сайта на уровне домена (пусто = глобальный fallback).

domains_stub — активная заглушка для выключенного домена (ключ current).

domains_comments — заметка к реальному домену (для админки).

config/admin.config
json
{
    "login": "admin",
    "password_hash": ""
}
Пароль ставится командой php bin/cli.php admin:passwd --name=admin --password=secret.

config/engine.config
json
{
    "rate_limit": {
        "max": 120,
        "window": 60,
        "ban": 900
    },
    "csp": "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'"
}
База данных
bash
php bin/cli.php db:migrate
Создаст var/damoony.db (SQLite) и таблицы users, news, files, migrations.

Для MySQL/MariaDB переопредели DSN через DI-конфиг или передай через Connection.

Веб-сервер
Apache
Убедись, что www/.htaccess используется и mod_rewrite включён. Виртуальный хост должен указывать DocumentRoot на www/.

Nginx
nginx
server {
    listen 80;
    server_name damoony.ru damoony3.ru;
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
composer install

php bin/cli.php db:migrate

php bin/cli.php admin:passwd --name=admin --password=secret

php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret

Открыть http://damoony3.ru/

Открыть http://damoony3.ru/admin/

(Команда user:new создаёт пользователя в БД, необходимого для входа на страницы с флагом "auth": true).

Разработка и тестирование
bash
export PHPRC=/path/to/php8.4
$PHP vendor/bin/phpunit -c _dev/phpunit.xml
$PHP vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
Или через Composer:

bash
composer test
composer stan
composer check
Структура каталогов
text
.
├── app/                    # Пользовательские домены
│   └── damoony.ru/
│       ├── www/            # Алиас (основной сайт)
│       │   └── algoritm/   # Страницы
│       ├── doc/            # Алиас (документация)
│       ├── .alliasglobal/  # Блоки (скрытая папка)
│       └── .alliasscheme/  # Схемы (скрытая папка)
├── bin/
│   └── cli.php
├── config/
│   ├── site.config
│   ├── engine.config
│   └── admin.config
├── migrations/
├── src/
├── var/                    # SQLite, логи, кэш
├── www/
│   ├── index.php
│   ├── admin/
│   │   └── index.php
│   └── media/              # Публичные файлы, CSS (в т.ч. /media/admin/)
├── _dev/                   # Тесты и тулинг
└── _doc/                   # Документация
Безопасность
Пароли — password_hash / password_verify (bcrypt) в базе данных.

CSRF — на всех POST-формах (в админке и на сайте).

Session — HttpOnly, SameSite=Lax, Secure при HTTPS.

Brute-force — бан IP на 15 минут после 5 неудач.

CSP — блокирует inline-скрипты, кроме style-src 'unsafe-inline'.

Файлы в www/media/ — только безопасные файлы, запрещены .php скрипты.
--- КОНЕЦ ФАЙЛА _docrebuild/install.md ---