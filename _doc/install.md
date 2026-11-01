Установка и запуск

Документ описывает развёртывание Damoony Engine с нуля. Проект находится в состоянии 99% production-ready (0 P0-блокеров). Перед деплоем прочитайте MANIFEST.md.

---

## Требования

- PHP >= 8.4 (расширения: `mbstring`, `json`, `pdo_mysql`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `curl`, `gd`).
- Composer.
- MariaDB 11+ (или MySQL 8+). SQLite — для тестов, не для production.
- Apache 2.4 + mod_rewrite или Nginx + PHP-FPM.
- Ollama (опционально, для работы AI-агента, рекомендуется Qwen 2.5 Coder 7B).
- `curl` + `openssl` (для AINET — обязательны).
- `gd` (для `content:store` — проверка размеров картинок; без gd не работает валидация «≥ 200×200»).

---

## Установка

```bash
git clone <repo-url> damoony
cd damoony
composer install
```

**Проверка расширений:**

```bash
php -m | grep -iE "curl|json|openssl|mbstring|pdo|gd"
php -r "var_dump(function_exists('curl_init'), function_exists('imagecreatetruecolor'));"
```

Если `curl` или `gd` отсутствуют — включите в `php.ini`:

```ini
extension=curl
extension=openssl
extension=gd
```

---

## Конфигурация

### `config/engine.config` — главный конфиг движка

БД, лимиты, CSP и настройки безопасности. Не коммитится (`.gitignore`).

```json
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
```

⚠️ `security.allow_php_blocks`: по умолчанию `false`. Включайте (`true`) только если вам действительно нужны блоки с сырым PHP (`custom`/`if`). Это защита от RCE.

### `config/site.config` — домены и алиасы

Не коммитится. Карта входящих хостов → реальных доменов.

```json
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
```

⚠️ Обязательно: self-mapping в `domains_real`. Ключ реального домена должен указывать на себя (`"damoony.ru": "damoony.ru"`). Без этого CLI-команды упадут с `Undefined array key`.

⚠️ **realDomain:** если `domains_real["damoony3.ru"] = "damoony.ru"`, то папка создаётся в `app/damoony.ru/`, а не `app/damoony3.ru/`. Все CLI-команды (`block`/`filler`/`scheme`/`skin`/`content`/`domain`/`media`) резолвят `realDomain` перед построением пути. Ошибка в конфиге = файлы не там, где их ищет движок.

### `config/admin.config` — доступ в админку

Логин, хеш пароля, список разрешённых языков.

```json
{
  "login": "admin",
  "password_hash": "",
  "domains_langs": ["ru", "en", "zh"],
  "default_lang": "ru"
}
```

⚠️ Пустой `password_hash` — админка недоступна. Задайте через `php bin/cli.php admin:passwd --name=admin --password=<strong>`.
⚠️ Валидация: login 3-32 символа `[a-zA-Z0-9_.-]`, password минимум 8 символов.

### `config/agent.config` — доступ для AI-агента

Файл для ограничения действий AI (whitelist доменов). Создайте его, если планируете использовать `bin/aicli.php`.

```json
{
  "allowed_domains": [
    "damoony3test.ru",
    "damoony.ru"
  ]
}
```

⚠️ Агент сможет вызывать `domain:new`, `page:write`, `allias:new`, `image:download` только для доменов из этого списка. `page:write`/`image:download` проверяют domain по `$allowedDomains` (закрыт P0). Это защищает боевые проекты от случайных изменений.

⚠️ Не коммитится (`.gitignore`). Добавьте `/config/agent.config` в `.gitignore`, если его там ещё нет.

### `config/ainet.config` — доступ для AINET (сеть)

Файл для безопасного выхода AI в интернет. Создайте копию из `config/ainet.config.example`:

```bash
cp config/ainet.config.example config/ainet.config
```

⚠️ Не коммитится (в `.gitignore`). Содержит whitelist URL-доменов (для `fetch`, `image:search`, `image:download`).

```json
{
  "allowed_domains": [
    "api.openverse.org",
    "api.unsplash.com",
    "images.unsplash.com",
    "api.pexels.com",
    "images.pexels.com",
    "commons.wikimedia.org",
    "upload.wikimedia.org",
    "thumb.wikimedia.org",
    "ru.wikipedia.org",
    "en.wikipedia.org",
    "api.github.com"
  ],
  "providers": {
    "default": "wikimedia",
    "keys": {
      "unsplash": "",
      "pexels": ""
    }
  },
  "timeouts": {"connect": 5, "read": 20},
  "limits": {
    "max_size": 10485760,
    "max_redirects": 3,
    "rate_per_min": 30,
    "min_image_dimension": 200
  },
  "cache_ttl_days": 7,
  "block_private_ips": true,
  "allowed_schemes": ["http", "https"],
  "allowed_ports": [80, 443],
  "allowed_mimes": [
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/webp",
    "image/svg+xml"
  ]
}
```

⚠️ **Fail-safe:** если `ainet.config` отсутствует или битый — `net:*` команды вернут `{"ok":false,"error":"AINET init failed: ..."}`. Без конфига интернет для AI полностью заблокирован.

⚠️ **Whitelist URL (ainet.config) независим от whitelist доменов (agent.config).** Оба проверяются для `image:download` (domain + url).

⚠️ **Провайдеры:** `wikimedia` — default (без ключа). `openverse` — fallback (без ключа). Unsplash/Pexels — опционально, если заполнить `keys`. Автофоллбэк primary → next работает только для default provider (при явном `--provider=...` fallback отключён).

⚠️ **URL whitelist:** `upload.wikimedia.org` и `thumb.wikimedia.org` — разные хосты. Если добавили один — добавьте оба. Аналогично для `commons.wikimedia.org`.

⚠️ `read_timeout: 20` — увеличено с 10 из-за медленных ответов Openverse/Cloudflare в РФ.

---

## База данных

Создайте базу:

```sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Примените миграции:

```bash
php bin/cli.php db:migrate
```

Команда идемпотентна. Поддерживает MariaDB/MySQL и SQLite (тесты).

⚠️ `--dir` ограничен подпапками `migrations/` (path traversal guard). Использовать: `--dir=migrations/archive` можно, `--dir=/etc` — нельзя.

---

## Веб-сервер

### Apache

`DocumentRoot` → `www/`. Включите `mod_rewrite`. Убедитесь, что `www/.htaccess` используется (`AllowOverride All` для `www/`).

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

    location /content/ {
        try_files $uri $uri/ /content.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

⚠️ **`/content/` — вне webroot.** Не добавляйте `root /path/to/damoony/content;`. Все запросы к `/content/<domain>/<allias>/<category>/<hash>.<ext>` идут через `www/content.php` (front controller, GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`).

⚠️ Если вы используете `www/.htaccess` для Apache — убедитесь, что `/content/` проксируется на `content.php`, а не пытается открыть файл напрямую (файлы лежат вне webroot, прямой доступ невозможен).

---

## Первый запуск

```bash
composer install
cp config/ainet.config.example config/ainet.config
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=<strong>
php bin/cli.php user:new --login=admin --email=admin@example.com --password=<strong>
```

- `admin:passwd` — администратор админки (`/admin/`).
- `user:new` — пользователь сайта (для страниц с `"auth": true`). Это разные учётки. Не путайте.

⚠️ Пароли: минимум 8 символов (и для `admin:passwd`, и для `user:new`).
⚠️ `user:new` проверяет уникальность login И email, а также `FILTER_VALIDATE_EMAIL`.

Откройте `http://<домен>/` (сайт) и `http://<домен>/admin/` (админка).

---

## Создание первого сайта

```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

Алиас `www` создаётся автоматически. Проверьте `config/site.config`.

⚠️ `page:new` создаёт страницу без схемы по умолчанию (`MANIFEST.md` §6.1). Шапки/футера на ней не будет. Для обычной страницы — `guide.md` §4, Вариант A (вызов SchemeRenderer в коде) или используйте `page:write --with_schema=1`.

⚠️ `--domain=a..ru` отклоняется (path traversal guard).

---

## Модель .rules.json (новая)

С версии «page-rules unify» каждая страница описывается тремя файлами с единым префиксом:

- `algoritm/about/algoritm_about.php`
- `algoritm/about/algoritm_about.rules.json`
- `algoritm/about/algoritm_about.editor.json`

Root алиаса: `algoritm/algoritm.{php,rules.json,editor.json}`.

Старая модель (`algoritm/.rules.json` + `{dir}/.rules.json`) больше не используется. Если на диске остались такие файлы — миграция:

```bash
php bin/cli.php migrate:page-rules --dry   # предпросмотр
php bin/cli.php migrate:page-rules          # боевой прогон
```

Идемпотентна. Шаги: (1) root, (2) подпапки, (3) нормализация полей, (4) создание пустых `.editor.json` для существующих `.php`.

⚠️ CLI-команда `page:toggle` теперь принимает `--name` (не `--path`), root — через `--name='__root__'`. Это breaking change относительно старых версий.

---

## Content (`/content/`) и Skin (`www/media/<domain>/<allias>/skin/`)

**Два разных хранилища — не смешивать.**

### Content (`/content/`) — runtime AI-контент, вне webroot

Структура:

```text
/content/                           # runtime AI-контент (вне webroot, .gitignore)
├── .trash/                         # карантин content:remove (runtime, .gitignore)
└── <domain>/<allias>/<category>/   # AI-контент
    ├── <hash>.<ext>
    └── <hash>.meta.json
```

- Запись — **только через CLI** `content:store` (вызывается из AICLI при `image:download`).
- Отдача — через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`).
- Удаление — `content:remove --force` (карантин в `/content/.trash/`).
- Дедупликация по hash.

### Skin (`www/media/<domain>/<allias>/skin/`) — git-tracked инфраструктура

Структура:

```text
www/media/                          # git-tracked инфраструктура
├── admin/                          # инфраструктура (git, руками)
├── shared/                         # инфраструктура (git, руками)
├── .trash/                         # карантин skin:remove (runtime, .gitignore)
└── <domain>/<allias>/skin/<name>/  # скины (git, руками)
    ├── skin.json
    ├── css/main.css
    ├── js/main.js
    └── img/
```

- Управление — **только через CLI** `skin:new/list/edit/remove`.
- `skin:edit` — только `css/main.css` и `js/main.js`, 1 MB, блок `@import`/`javascript:`.
- Удаление — `skin:remove --force` (карантин в `www/media/.trash/`).

⚠️ **Не путайте:** `www/media/<domain>/<allias>/skin/` — git, руками. `/content/<domain>/<allias>/<category>/` — runtime, вне webroot, только через CLI `content:store`. Смешивание = потеря файлов при очистке карантина или случайный коммит AI-контента в git.

⚠️ **`.gitignore` в `www/media/`** — локальный, с `*` + `!` un-ignore для `admin/`, `shared/`, `skin/`, `.trash/`. Не редактируйте root `.gitignore` для media — используйте локальный.

⚠️ **Корневой `.gitignore`** должен содержать `/content/` (runtime AI-контент). Проверьте:

```bash
grep -n "^/content/" .gitignore
# → должно быть /content/ (или /content)
```

**Проверка `.gitignore`:**

```bash
git check-ignore -v content/example.ru/www/coffee/abc123.png
# → .gitignore:N:/content/  content/example.ru/www/coffee/abc123.png (ignored)

git check-ignore -v www/media/damoony.ru/www/skin/default/css/main.css
# → (пусто, NOT ignored)
```

---

## Развёртывание по IP (intranet, staging)

В `config/site.config` добавьте IP как ключ в `domains_real` (без порта):

```json
"domains_real": {
    "192.168.1.10": "example.ru",
    "example.ru": "example.ru"
}
```

Откройте `http://192.168.1.10/`.
⚠️ Порт и trailing-dot нормализуются автоматически (stage-3). Для нескольких алиасов нужны DNS/hosts.

---

## Разработка и тестирование

```bash
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php
```

Или через composer:

```bash
composer test
composer stan
composer cs
composer check   # stan + cs + test
```

**Ожидаемые метрики:**

- PHPUnit: **570 тестов, 1671 assertion**, 1 skipped (`LinkTraversalTest` — требует dev-сервер `127.0.0.1:8080`, прогоняется в CI). Тестовых файлов — 87.
- PHPStan: level 8, 0 errors, 240 файлов (`src/`, `bin/`, `www/`).
- PHP-CS-Fixer: 0 issues, 240 файлов (`src/`, `bin/`, `www/`, `tests/`).

---

## Безопасность (перед production)

- Пароли — `password_hash`/`password_verify` (bcrypt). `admin:passwd` и `user:new` делают это. Минимум 8 символов.
- CSRF — на всех POST (админка и сайт). Destructive операции защищены.
- Сессии — HttpOnly, SameSite=Lax, Secure при HTTPS.
- Brute-force — бан IP на 15 минут после 5 неудач.
- CSP — блокирует inline-скрипты (CodeMirror локальный из `www/media/admin/`).
- Визуальные редакторы удалены для страниц и блоков (CodeMirror). Drag&Drop оставлен только для Схем (через CLI `scheme:save`).
- Флаг `security.allow_php_blocks=false` по умолчанию блокирует генерацию сырого PHP.
- AI-агенты ограничены `config/agent.config` (домены) + `config/ainet.config` (URL). `page:write` и `image:download` проверяют domain по `allowed_domains`.
- `.htaccess`: dev-правило для `/tests/` удалено (было в старых версиях).

**CLI-безопасность:**

- Path traversal guard во всех командах с `--domain`/`--name` (`str_contains('..')` + regex).
- Destructive-команды (`domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `content:remove`, `skin:remove`, `trash:clear`) работают через карантин (`*_trash_<timestamp>/`) и требуют `--force`.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- `db:migrate --dir` ограничен `migrations/`.
- `user:new` проверяет уникальность login и email.
- `content:store` — MIME whitelist, размер ≤ 10 MB, размеры ≥ 200×200, дедупликация по hash.
- `skin:edit` — whitelist файлов (`css/main.css`, `js/main.js`), 1 MB, блок `@import`/`javascript:`.

**AINET-безопасность (SSRF):**

- Только GET (`CURLOPT_HTTPGET=true`). POST/PUT/DELETE запрещены.
- Whitelist URL-доменов (`config/ainet.config.allowed_domains`), точный матч, без wildcard.
- Блок private IP (`127/8`, `10/8`, `172.16/12`, `192.168/16`, `169.254/16`, `::1`, `fc00::/7`, `fe80::/10`).
- Запрет scheme (только http/https) и портов (только 80/443).
- Запрет `user:pass` в URL.
- Ручные редиректы (`CURLOPT_FOLLOWLOCATION=false`), повторный UrlGuard на каждый Location.
- Rate limit 30 req/min (в конфиге; применение — P2).
- `max_size` 10 MB, timeout connect 5s / read 20s.
- Кэш 7 дней (`var/ainet-cache/`).

**Content-безопасность:**

- Запись только через CLI `content:store` (AINET не пишет на диск).
- MIME whitelist (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`).
- Размер ≤ 10 MB, размеры ≥ 200×200 (для не-SVG).
- Дедупликация по hash (sha256, 16 символов).
- `domain` — из `config/agent.config.allowed_domains`.
- Удаление — только через `content:remove --force` (в карантин `/content/.trash/`).
- Отдача — через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`).

**Skin-безопасность:**

- Запись только через CLI `skin:edit` (`css/main.css`, `js/main.js`).
- Размер ≤ 1 MB.
- Блок `@import` и `javascript:` URL.
- Удаление — только через `skin:remove --force` (в карантин `www/media/.trash/`).

---

## Проверка после установки

```bash
php bin/cli.php structure:check
```

✅ **Severity-aware:**

- `✗` (error) — критично (exit 1): отсутствует `.php`, отсутствует `algoritm/algoritm.rules.json` (root), отсутствует схема.
- `⚠` (warning) — некритично (exit 0): отсутствует `.editor.json`, устаревший `{dir}/.rules.json`, филлер без слота.

**Восстановить пропущенные файлы:**

```bash
php bin/cli.php structure:repair --domain=example.ru
```

Восстанавливает root-страницы через PageCompiler (`algoritm.php`, `algoritm.rules.json`, `algoritm.editor.json`), `algoritm.rules.json` для схем, `{name}.rules.json/.editor.json` для блоков. Path traversal guard.

**Если видите warning про устаревший `.rules.json` — миграция:**

```bash
php bin/cli.php migrate:page-rules
```

**Очистка карантина (6 уровней, после destructive-операций):**

```bash
php bin/cli.php trash:clear                          # dry-run
php bin/cli.php trash:clear --force --older=7d       # только старше 7 дней
php bin/cli.php trash:clear --force --domain=example.ru
```

Обходит **6 уровней**: `app/`, `app/<domain>/`, `.alliasscheme/`, `.alliasglobal/`, `/content/.trash/`, `www/media/.trash/`.

**Проверка AINET:**

```bash
echo '{"command": "net:status", "args": {}}' | php bin/aicli.php
# Ожидаемо: {"ok":true,"data":{"providers":["openverse","wikimedia"],"default_provider":"wikimedia",...}}

echo '{"command": "image:search", "args": {"query": "coffee", "count": 2}}' | php bin/aicli.php
# Ожидаемо: {"ok":true,"data":{"provider":"wikimedia","results":[...]}}
```

**Проверка Content:**

```bash
php bin/cli.php content:list --domain=example.ru
# Ожидаемо: пустой список или существующие файлы

curl -I http://example.ru/content/example.ru/www/coffee/<hash>.jpg
# Ожидаемо: 200 OK, Content-Type, ETag, Cache-Control
```

**Проверка Skin:**

```bash
php bin/cli.php skin:list --domain=example.ru --allias=www
# Ожидаемо: пустой список или существующие скины
```

**Проверка Roles / Navigator:**

```bash
echo '{"command": "ai:start", "role": "architect"}' | php bin/aicli.php
# Ожидаемо: {"ok":true,"data":{"state":{"domains":[...]},"whitelist":[...]},"role":"architect","next":[...]}
```

---

## Полезные ссылки

- `MANIFEST.md` — канон: ✅/🟡/❌, P0/P1/P2, backlog, видение.
- `readme.md` — обзор и быстрый старт.
- `cli.md` — CLI-команды (37 команд).
- `admin.md` — админка.
- `guide.md` — руководство разработчика.
- `architecture.md` — жизненный цикл (включая AI-слой, AINET, content, skin).
- `ai_agent.md` — интеграция AI-агентов (сценарий «сайт с картинками»).
- `agent_architecture.md` — Navigator + 9 ролей, Multi-Agent оркестрация.
- `ainet.md` — дизайн AINET (SSRF, HttpClient, Cache, ImageSearch, Facade).
- `contracts.md` — контракты и ограничения.