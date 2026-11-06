# Установка и развёртывание

Документ описывает установку Damoony Engine с нуля. Для написания кода после установки читайте `DEV.md`, для управления — `CLI.md`.

---

## 1. Требования

- **PHP >= 8.4** (расширения: `mbstring`, `json`, `pdo_mysql`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `curl`, `gd`).
- **Composer**.
- **MariaDB 11+** (или MySQL 8+). SQLite — только для тестов.
- **Apache 2.4** + `mod_rewrite` или **Nginx** + PHP-FPM.
- **Python 3.10+** (для запуска мультиагентной системы `ai_agent/brain.py`).
- **Ollama** (опционально, для AI-агента, рекомендуется Gemma 4 или Qwen 2.5).
- `curl` + `openssl` (обязательны для AINET).
- `gd` (для `content:store` — проверка размеров картинок).

---

## 2. Установка зависимостей

```bash
git clone https://github.com/DCoD-85/cms-damoony.ru.git damoony
cd damoony
composer install
```

**Проверка расширений:**
```bash
php -m | grep -iE "curl|json|openssl|mbstring|pdo|gd"
php -r "var_dump(function_exists('curl_init'), function_exists('imagecreatetruecolor'));"
```

---

## 3. Конфигурация (config/*.config)

Все конфиги не коммитятся (они в `.gitignore`), кроме `config/models.config` (трекается, секретов не содержит — см. §3.6). Создаются копированием из примеров или вручную.

### 3.1. `config/engine.config` — главный конфиг движка
```json
{
  "rate_limit": { "max": 120, "window": 60, "ban": 900 },
  "security": { "allow_php_blocks": false },
  "csp": "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'",
  "db": {
    "driver": "mysql",
    "host": "127.0.0.1",
    "dbname": "your_database_name",
    "user": "your_user",
    "password": "your_password"
  }
}
```
⚠️ `security.allow_php_blocks` по умолчанию `false` (защита от RCE). Включайте только если нужны блоки с сырым PHP.

### 3.2. `config/site.config` — домены и алиасы
```json
{
  "language": "ru",
  "domains_real": {
    "damoony.ru": "damoony.ru"
  },
  "domains_available": {
    "damoony.ru": true
  },
  "domains_namespace": {
    "damoony.ru": "damoony_ru"
  },
  "alliases_real": {
    "damoony.ru": { "www": "www", "doc": "doc", "agent": "agent" }
  },
  "alliases_available": {
    "damoony.ru": { "www": true, "doc": true, "agent": true }
  },
  "alliases_langs": {
    "damoony.ru": { "www": ["ru"] }
  }
}
```
⚠️ **Self-mapping обязателен:** ключ реального домена должен указывать на себя (`"damoony.ru": "damoony.ru"`).
⚠️ Если `domains_real["damoony3.ru"] = "damoony.ru"`, то папка создаётся в `app/damoony.ru/` (realDomain). 

### 3.3. `config/admin.config` — доступ в админку
```json
{
  "login": "admin",
  "password_hash": "",
  "domains_langs": ["ru", "en", "zh"],
  "default_lang": "ru"
}
```
⚠️ Пароль задаётся через CLI: `php bin/cli.php admin:passwd --name=admin --password=[strong]`.

### 3.4. `config/agent.config` — доступ для AI
```json
{
  "allowed_domains": ["damoony.ru"]
}
```
Ограничивает домены, в которых AI может вызывать `page:write`, `block:write` и `image:download`.

### 3.5. `config/ainet.config` — доступ в интернет (AINET)
```bash
cp config/ainet.config.example config/ainet.config
```
Содержит whitelist URL-доменов (для `fetch`, `image:search`), лимиты, таймауты. Если файл отсутствует или битый — `net:*` команды вернут ошибку (fail-safe).

---

### 3.6. Модели AI (config/models.config)

Используется ModelRouter (см. _doc/orchestrator.md §3.3). Маппинг role -> model для внутреннего AI-контура AIUI.

Пример структуры (4-пробельный отступ = код в markdown):

    {
      "default": "deepseek-coder:6.7b",
      "roles": {
        "reporter":  "gemma4:e4b",
        "architect": "deepseek-coder:6.7b",
        "designer":  "deepseek-coder:6.7b"
      }
    }

Ключи с подчёркиванием в начале (`_readme` и т.п.) игнорируются кодом и служат документацией. Файл коммитится в git (секретов не содержит).

**Важно:** без `models.config` AIUI не запустится. ModelRouter вернёт fallback `qwen2.5-coder:7b`; если такой модели в Ollama нет — Orchestrator упадёт с ошибкой.

## 4. База данных

Создайте базу:
```sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Примените миграции:
```bash
php bin/cli.php db:migrate
```

---

## 5. Веб-сервер

**DocumentRoot** должен указывать на папку `www/`.

### Nginx
```nginx
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

    # Front controller для отдачи контента (картинок)
    location /content/ {
        try_files $uri $uri/ /content.php?$query_string;
    }

    # AIUI preview (изолированная сессия)
    location /admin/preview/ {
        rewrite ^/admin/preview/([a-f0-9]+)/([a-z0-9_]+)/?(.*)$ /admin/aipreview.php?tab=$1&alias=$2&path=/$3 last;
    }

    # Публичные формы (POST endpoint)
    location = /submit {
        rewrite ^ /forms.php last;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # Таймауты для AIUI (генерация может занимать несколько минут)
        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
        fastcgi_connect_timeout 60s;
    }
}
```
⚠️ `/content/` — вне webroot (файлы лежат в корне проекта, не в `www/`). Запросы к `/content/...` проксируются на `www/content.php`. Прямой доступ к папке `/content/` невозможен.

### Apache
Включите `mod_rewrite`. Убедитесь, что в `www/.htaccess` разрешены переопределения (`AllowOverride All`). Настройте проксирование `/content/` на `content.php`.

---

## 6. Права доступа (Linux)

Пользователь веб-сервера (например, `www-data`) должен иметь права на запись в:
- `var/` — логи, кэш, rate-limit, сессии (`var/wizard/`, `var/sessions/`)
- `content/` — runtime-контент AI
- `www/media/` — скины, сгенерированные CSS, **сессионные медиа-копии** (`.sess_*`)
- `app/` — **контент доменов и сессии AIUI**: админка создаёт/меняет файлы через `bin/cli.php` (`domain:new`, `allias:new`, `page:write`, `session:start`, `session:apply`). Без прав на `app/` — 500 при любом сохранении из UI.

    chown -R www-data:www-data app/ content/ var/ www/media/
    chmod -R 775 app/ content/ var/ www/media/

⚠️ **Не давайте права на `config/`**: конфиги содержат секреты (DB password, API keys) и не должны быть доступны веб-процессу. Управление — только через CLI от владельца (root/sudo), не из админки.


---

## 7. Первый запуск

```bash
php bin/cli.php admin:passwd --name=admin --password=[strong]
php bin/cli.php user:new --login=admin --email=admin@example.com --password=[strong]
```
*`admin:passwd` — администратор админки (`/admin/`).*
*`user:new` — пользователь сайта (для страниц с `"auth": true`).*

Откройте `http://[domain]/` и `http://[domain]/admin/`.

---

## 8. Создание первого сайта (вручную или через AI)

**Вручную:**
```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

**Через AI (E2E):**
Убедитесь, что Ollama запущена (`ollama run gemma4:e4b`), и выполните:
```bash
python -m ai_agent.brain <domain> <allias> "<brief>" <model_name>
# Пример:
python -m ai_agent.brain damoony3test.ru coffee "Сделай лендинг IT-агентства" gemma4:e4b
```

---

## 9. Проверка после установки

```bash
php bin/cli.php structure:check
```
Команда проверит наличие всех 3 файлов, корневых правил и схем.

Возможные итоги:
- **0 ошибок, 0 предупреждений:** `✓ Все проверки пройдены успешно!`, exit 0.
- **0 ошибок, N предупреждений:** `Все критичные проверки пройдены, предупреждений: N`, exit 0.
- **N ошибок:** `Ошибки:` + `✗ Найдено ошибок: N` + `FAILED`, exit 1.
