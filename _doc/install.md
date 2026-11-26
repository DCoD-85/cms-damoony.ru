# Установка

## 1. Требования

- PHP ≥ 8.5: `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `curl`, `gd`;
- Composer;
- MariaDB 11+ / MySQL 8+;
- Nginx + PHP-FPM;
- Linux: пользователь `www-data` (или свой).

## 2. Код и зависимости

```bash
git clone https://github.com/DCoD-85/cms-damoony.ru.git /var/www/damoony
cd /var/www/damoony
git checkout refactor/site-config-v2
composer install --no-dev
```

## 3. База

```sql
CREATE DATABASE damoony CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'damoony'@'127.0.0.1' IDENTIFIED BY 'strong-db-pass';
GRANT ALL PRIVILEGES ON damoony.* TO 'damoony'@'127.0.0.1';
```

## 4. Конфиги

`config/engine.config` (не коммитится):

```json
{
    "db": {
        "driver": "mysql",
        "host": "127.0.0.1",
        "dbname": "damoony",
        "user": "damoony",
        "password": "strong-db-pass"
    },
    "security": {
        "allow_php_blocks": false,
        "admin_ips": ["127.0.0.1"],
        "session_domain": ".example.ru"
    }
}
```

`config/site.config` создаётся командами `new`/`domain:*`; вручную можно начать с `config/site.config.example`.

## 5. Инициализация

```bash
php bin/cli.php cli:init --name=owner
php bin/cli.php db:migrate
```

## 6. Первый сайт

```bash
php bin/cli.php new --domain=example.ru --assembly=minimal --force
```

⚠️ `--force` удаляет старые файлы проекта и пересобирает алиасы. На существующем сайте без бэкапа не использовать.

Создаёт проект в `domains_app`, папку `app/example.ru/`, алиас `www`, языки из сборки.

## 7. Админка

Админка — отдельный проект `admin` с зеркалом IP и страницами `app/.admin/www/algoritm/...`.

Добавить в `config/site.config`:

```json
"admin": {
    "folder": ".admin",
    "namespace": "admin_server",
    "mirrors": {
        "127.0.0.1": { "available": true }
    },
    "langs": ["ru", "en"],
    "aliases": {
        "www": { "available": true, "langs": ["ru", "en"] }
    },
    "scheme": "admin",
    "default_alias": "www",
    "stub": "maintenance"
}
```

Создать админа:

```bash
php bin/cli.php admin:create --login=root --password='strong-pass-1' --role=superadmin
```

Для доменного админа:

```bash
php bin/cli.php admin:create --login=docs --password='strong-pass-2' --role=domainadmin --scope=example.ru
```

## 8. Права

```bash
chown -R www-data:www-data app www/media var content
chown root:www-data config/engine.config config/site.config
chmod 640 config/engine.config
chmod 660 config/site.config
chmod 600 var/cli.identity
```

`site.config` должен быть доступен на запись группе `www-data`: админка меняет алиасы/зеркала. `engine.config` — только чтение.

## 9. Nginx

Корень — `www/`, front controller — `index.php`:

```nginx
root /var/www/damoony/www;
index index.php;

location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
}
```

IP-админка — отдельный vhost, см. `_doc/deploy.md`.

## 10. Проверка

```bash
php bin/cli.php structure:check
php bin/cli.php health:check
php bin/cli.php domain:list
```

- сайт: `http://example.ru/ru/`;
- админка: `http://127.0.0.1/ru/`.

## Обновление

```bash
git fetch && git reset --hard origin/refactor/site-config-v2
composer install --no-dev
php bin/cli.php db:migrate --force
php bin/cli.php structure:check
```
