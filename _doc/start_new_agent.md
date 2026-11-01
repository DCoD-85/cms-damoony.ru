Start Here: Briefing для AI-агента

Точка входа для AI-агента (или нового разработчика). Прочитай целиком до любых действий.

Тебе выдаётся комплект документов. Этот файл — про окружение и правила работы. `MANIFEST.md` — канон: что реализовано (✅/🟡/❌), известные дефекты, баги P0/P1/P2, backlog, видение. Остальные документы (`architecture.md`, `contracts.md`, `guide.md`, `cli.md`, `admin.md`, `ai_agent.md`, `agent_architecture.md`, `ainet.md`) — производные. Читай `MANIFEST.md` наравне с этим файлом.

---

## 1. Идентификация проекта

**Название:** Damoony Engine.
**Назначение:** лёгкий PHP 8.4 движок для мультидоменных сайтов с архитектурой **CLI-First** и нативной интеграцией AI-агентов (9 ролей + Navigator).
**Репозиторий:** https://github.com/DCoD-85/cms-damoony.ru/
**Ветка:** `main`.
**Стек:** PHP 8.4, MariaDB 11.8, Apache2, Composer (PSR-4, PSR-3, PSR-11), PHPUnit 10, PHPStan level 8, Ollama (Qwen 2.5 Coder 7B для AI-агентов), curl + openssl + gd (AINET + content).

---

## 2. Характер работы

- Разговорность — минимум. Отчёт по факту: что сделал, что проверено, что осталось. Без воды, без «отличная задача», без пересказа очевидного.
- Не объясняй, что ты собираешься делать — делай. Если нужен план (задача большая, риск сломать) — короткий список шагов, не эссе.
- Не дублируй документацию в ответах. Ссылайся на файл и раздел (`MANIFEST.md` §7.1), не цитируй.
- Если не уверен — спроси. Одна короткая формулировка вопроса. Не «делать как в прошлый раз».
- Не изобретай. Следуй правилам проекта, даже если считаешь, что «так лучше». Предложить изменение — можно, внедрять без согласования — нет.

---

## 3. Окружения

### 3.1. Локальное (разработка)

- **Железо:** Intel Core i9-14900HX, 32GB RAM, Windows 11.
- **Веб-сервер:** OSPanel 5.4.3.
- **Корень проекта:** `E:\OSPanel543\domains\damoony3.ru\`
- **Доступ:** gitbash (терминал) + VSCode.
- **Роль:** вся разработка, тесты, PHPStan, CS-Fixer, коммиты.

**Обязательные бинарники (только они):**

- PHP (CLI): `E:\OSPanel543\modules\php\PHP_8.4\` (версия 8.4.25)
- MariaDB client: `E:\OSPanel543\modules\database\MariaDB-11.8-Win10\bin\` (версия 11.8)

**Обязательные расширения PHP** (проверить `php -m`):
`curl`, `json`, `openssl`, `mbstring`, `pdo_mysql`, `pdo_sqlite`, `gd`, `fileinfo`, `tokenizer`, `xml`.

**Запрещено:** PHP/MariaDB из winget, глобальный PATH, Composer из AppData. Если `php --version` ≠ 8.4.25 из OSPanel — исправь PATH, потом продолжай.

### 3.2. Production

- **Железо:** i7, 8GB RAM, 128GB SSD + 1TB HDD.
- **ОС:** Debian 13, Apache 2.
- **PHP 8.4.25** (с curl, openssl, gd), MariaDB 11.8.
- **Корень:** `/var/www/damoony.ru/`.
- **Роль:** только `git pull`. Прямое редактирование на сервере запрещено.

---

## 4. Git (жёсткие правила)

- Единственный источник истины — GitHub `main`.
- Цепочка: local edits → `git commit` → `git push origin main` → `git pull` на production.
- Никаких прямых правок на production (ни SSH, ни админка, кроме контента/настроек через UI).
- Не пушить без зелёных `phpunit`, `phpstan`, `php-cs-fixer`.
- Не коммитить `config/engine.config`, `config/site.config`, `config/admin.config`, `config/agent.config`, `config/ainet.config` — они в `.gitignore`.
- Регулярная синхронизация: после каждой логической порции — commit + push.

---

## 5. Разрешено и запрещено

### ✅ Разрешено

- Создавать/редактировать файлы в `src/`, `_dev/tests/`, `_doc/`, `lang/`, `stubs/`, `app/<domain>/`.
- Запускать CLI: `php bin/cli.php <command>` (Единственный источник истины).
- Запускать тесты, PHPStan, CS-Fixer (см. §8).
- Обновлять `_doc/`.

### ❌ Запрещено

- **Production:** прямое редактирование `/var/www/damoony.ru/` по SSH — только `git pull`.
- **Ядро без явного запроса владельца:** `src/Core/`, `src/Http/Kernel.php`, `src/App/AppFactory.php`, `bin/aicli.php`, `bin/ainet.php`.
- **Git:** пуш в `main` при красных проверках; коммит `config/*.config`; изменение `composer.lock` без запроса.

---

## ⚠️ Текущий статус кода (см. `MANIFEST.md` §7)

- Все **P0 и P1 закрыты**. RCE, CSRF, XSS, Path Traversal, SSRF — устранены.
- Внедрено **Правило 3 файлов** (`.php`, `.rules.json`, `.editor.json`) для всех сущностей (Страница, Блок, Филлер, Схема, Скин).
  Для страниц все три файла имеют единый префикс: `algoritm_{name}.{php,rules.json,editor.json}` в одной папке.
  Root алиаса: `algoritm/algoritm.{php,rules.json,editor.json}`.
- Визуальные редакторы для Страниц и Блоков **удалены** (переведены на CodeMirror). Drag&Drop остался только для Схем (через CLI `scheme:save`).
- Создан безопасный PageCompiler (JSON → PHP) для генерации страниц AI-агентом (защита от RCE). Используется также для stub-страниц (`domain:new`, `allias:new`, `structure:repair`).
- Создан безопасный AI-фасад (`bin/aicli.php`) — единственная точка входа для AI. **9 ролей** (`src/Ai/Roles.php`) + **Navigator** (`src/Ai/Navigator.php`, `next[]` HATEOAS). Роль `*` (default) = union всех ролей + специфичные. Связан с Ollama (Qwen 2.5 Coder 7B). Успешно генерирует 10-страничные сайты с картинками через `agent_test.py`.
- **AINET** (библиотека `src/Ainet/`) — безопасный выход в интернет: SSRF-защита (UrlGuard), HttpClient (GET-only, ручные редиректы), Cache (TTL 7 дней), ImageSearch (wikimedia default, openverse fallback), AinetFacade. **AINET не пишет на диск** — bytes → CLI `content:store`.
- **Content** (`/content/`): runtime-хранилище AI-контента, **вне webroot**, `.gitignore`. Отдаётся через `www/content.php` (GET/HEAD, ETag, 304, SVG-CSP, auth по `page_refs`). Дедупликация по hash. Запись только через `content:store`.
- **Skin** (`www/media/<domain>/<allias>/skin/`): git-tracked инфраструктура. Управление через CLI `skin:new/list/edit/remove`. `skin:edit` — только `css/main.css` и `js/main.js`, 1 MB, блок `@import`/`javascript:`.
- **Page-слой**: PagePath, PageRules, PageNamespace — единый резолвинг путей/правил/namespace для всех page-команд.
- **Миграция**: `migrate:page-rules` — одноразовый перевод старых `.rules.json` на новую модель. Идемпотентна.
- **CLI-аудит** (**39 команд**): path traversal guard, карантин (**6 уровней**) для destructive, idempotent remove, realDomain, `scheme:*` через CLI, `content:*` CLI, `skin:*` CLI, `*:list` (6 команд с `--json`), Fs::rmrf (единый рекурсивный).
- **Два whitelist доменов**: `config/agent.config` (`app/*` + `image:download.domain`) и `config/ainet.config` (URL `net:*`).
- **Рабочий E2E-оркестратор**: `agent_test.py` (Ollama ↔ aicli). `ai_agent/` (Planner→Builder→Content) — прототип, не используется (P3).
- **Git-гигиена**: untrack `/content/`, `app/damoony3test.ru/`, `config/ainet.config`, `config/agent.config`. `.gitignore` — обновлён.

⚠️ **Breaking change:** `page:toggle --path` заменён на `--name` + `--parent` (root — `--name='__root__'`).

⚠️ `vars:extract` удалён (мёртвый код). Миграция старой модели — `migrate:page-rules`.

⚠️ **AINET:** Openverse переехал с `.engineering` на `.org` (301). Default provider = `wikimedia`, `read_timeout = 20s`. Автофоллбэк primary → next (только default provider).

⚠️ **Content vs Skin:** два разных хранилища, не смешивать. `/content/` — runtime AI-контент (вне webroot). `www/media/<domain>/<allias>/skin/` — git-tracked инфраструктура. Смешивание = потеря файлов при очистке карантина или случайный коммит AI-контента в git.

---

## 6. Ключевые пути

| Что | Путь |
|---|---|
| Канон | `_doc/MANIFEST.md` |
| Ядро | `src/` |
| AINET (библиотека) | `src/Ainet/` (UrlGuard, HttpClient, Cache, ImageSearch, AinetFacade) |
| Roles + Navigator | `src/Ai/Roles.php`, `src/Ai/Navigator.php` |
| Content front controller | `src/Content/ContentResponder.php`, `www/content.php` |
| Домены | `app/<domain>/` |
| Страницы алиаса | `app/<domain>/<allias>/algoritm/` (3 файла: `algoritm_{name}.{php,rules.json,editor.json}`) |
| Root алиаса | `app/<domain>/<allias>/algoritm/algoritm.{php,rules.json,editor.json}` |
| Глобальные блоки | `app/<domain>/.alliasglobal/<name>/` (3 файла) |
| Глобальные схемы | `app/<domain>/.alliasscheme/<name>/` (3 файла) |
| **Content (runtime)** | **`/content/<domain>/<allias>/<category>/<hash>.<ext>` + `<hash>.meta.json`** (вне webroot, `.gitignore`) |
| **Skin (git-tracked)** | **`www/media/<domain>/<allias>/skin/<name>/{css,js,img,skin.json}`** |
| Медиа (инфраструктура) | `www/media/admin/`, `www/media/shared/` |
| **Карантин (6 уровней)** | `app/<domain>_trash_<ts>/`, `app/<domain>/<allias>_trash_<ts>/`, `app/<domain>/.alliasscheme/<name>_trash_<ts>/`, `app/<domain>/.alliasglobal/<name>_trash_<ts>/`, `/content/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/`, `www/media/.trash/<domain>_<allias>_skin_<name>_trash_<ts>/` |
| Конфиги | `config/*.config` (включая `agent.config` для AI, `ainet.config` для сети) |
| Переводы | `lang/{ru,en,zh}.php` |
| Заглушки 503 | `stubs/*.html` |
| Тесты | `_dev/tests/` (87 файлов, 570 тестов) |
| Документация | `_doc/` |
| Runtime | `var/` (+ `var/ainet-cache/`) |
| Миграции | `migrations/*.sql` |
| Вход сайта | `www/index.php` |
| Вход админки | `www/admin/index.php` |
| **Вход Content** | **`www/content.php`** |
| Вход CLI | `bin/cli.php` |
| Вход AI-фасада | `bin/aicli.php` (единственный для AI) |
| Standalone AINET | `bin/ainet.php` (не для AI, только отладка) |
| **E2E-оркестратор** | **`agent_test.py`** (рабочий) |
| **Прототип (не используется)** | **`ai_agent/`** (Planner→Builder→Content, P3) |

---

## 7. Workflow

1. Прочитать задачу.
2. Прочитать `_doc/MANIFEST.md` §6–7 — что реализовано, какие есть ограничения (P2), чего не делать. Это не опционально.
3. `git status`, `git log --oneline -3`.
4. Внести изменения.
5. Прогнать локально:

```bash
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php
```

6. Если зелёное — commit + push:

```bash
git add <files>
git commit -m "<scope>: <what and why>"
git push origin main
```

7. Production (только владелец):

```bash
cd /var/www/damoony.ru && git pull origin main && systemctl reload apache2
```

8. Обновить `_doc/`, если изменилось поведение/API/конфиги.

**Перед destructive-операциями** (`domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `content:remove`, `skin:remove`, `trash:clear --force`, `migrate:page-rules`, любые правки существующих файлов) — `git commit`. Все destructive CLI-команды требуют `--force` и работают через карантин (**6 уровней**).

---

## 8. Быстрые команды

```bash
# Бинарники OSPanel
which php && php --version   # 8.4.25 из E:\OSPanel543\modules\php\PHP_8.4\
mysql --version              # 11.8 из E:\OSPanel543\modules\database\MariaDB-11.8-Win10\bin\

# Если нет — в gitbash:
export PATH="/e/OSPanel543/modules/php/PHP_8.4:$PATH"
export PATH="/e/OSPanel543/modules/database/MariaDB-11.8-Win10/bin:$PATH"

# Расширения PHP
php -m | grep -iE "curl|json|openssl|mbstring|pdo|gd"
php -r "var_dump(function_exists('curl_init'), function_exists('imagecreatetruecolor'));"

# Тесты / анализ / стиль (570 тестов, 1671 assertion, 87 файлов)
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run

# Диагностика и восстановление структуры
php bin/cli.php structure:check                  # severity-aware: warning ≠ error
php bin/cli.php structure:repair --domain=example.ru

# Миграция .rules.json на новую модель (после рефакторинга)
php bin/cli.php migrate:page-rules --dry         # предпросмотр
php bin/cli.php migrate:page-rules                # боевой прогон (идемпотентно)

# Очистка карантина (6 уровней)
php bin/cli.php trash:clear                       # dry-run
php bin/cli.php trash:clear --force --older=7d    # только старше 7 дней
php bin/cli.php trash:clear --force --domain=example.ru

# Content (runtime, вне webroot)
php bin/cli.php content:list --domain=example.ru [--allias=www] [--category=coffee]
php bin/cli.php content:remove --domain=example.ru --allias=www --category=coffee --hash=a3f5c1e9 --force

# Skin (git-tracked, www/media/<d>/<a>/skin/)
php bin/cli.php skin:list --domain=example.ru --allias=www
php bin/cli.php skin:remove --domain=example.ru --allias=www --name=default --force

# *:list (инспекция состояния)
php bin/cli.php domain:list
php bin/cli.php allias:list --domain=example.ru
php bin/cli.php page:list --domain=example.ru --allias=www --json
php bin/cli.php scheme:list --domain=example.ru
php bin/cli.php block:list --domain=example.ru
php bin/cli.php filler:list --domain=example.ru --allias=www

# Миграции БД
php bin/cli.php db:migrate

# Список всех команд
php bin/cli.php help                              # 37 команд

# Проверка AI-фасада (требуется config/agent.config + config/ainet.config)
echo '{"command": "engine_status", "args": {}}' | php bin/aicli.php
echo '{"command": "net:status", "args": {}}' | php bin/aicli.php
echo '{"command": "image:search", "args": {"query": "coffee", "count": 2}}' | php bin/aicli.php

# Проверка Roles / Navigator
echo '{"command": "ai:start", "role": "architect"}' | php bin/aicli.php
# → {"ok":true,"data":{"state":{"domains":[...]},"whitelist":[...]},"role":"architect","next":[...]}

# Standalone AINET (отладка, не для AI)
echo '{"command": "net:status", "args": {}}' | php bin/ainet.php

# Проверка Content front controller (браузер / curl)
curl -I http://example.ru/content/example.ru/www/coffee/<hash>.jpg
# → 200 OK, Content-Type, ETag, Cache-Control
```

---

## 9. Эскалация

Остановиться и спросить владельца, если:

- Задача неоднозначна.
- Риск сломать production.
- Нужны права (SSH, DB root).
- Собираешься трогать ядро (`src/Core/`, `Kernel.php`, `AppFactory.php`, `bin/aicli.php`, `bin/ainet.php`) без явного запроса.
- Собираешься делать destructive-операцию без коммита.
- Собираешься менять логику работы AI (`aicli.php`, `PageCompiler`, `AinetFacade`, **`Roles`/`Navigator`**) — это контракт AI-слоя.
- Собираешься менять модель `.rules.json` (`PagePath`, `PageRules`, `FileRouter`) — это критическая инфраструктура роутинга.
- Собираешься менять механизм карантина, path traversal guard или SSRF-защиту — это основа безопасности.
- Собираешься менять структуру `www/media/` (три зоны) **или `/content/`** — это может привести к случайному коммиту AI-контента в git или потере файлов.
- Собираешься менять `www/content.php` / `ContentResponder` — это front controller и auth по `page_refs` (безопасность).
- Собираешься менять `skin:edit` whitelist (`css/main.css`, `js/main.js`, 1 MB, `@import`/`javascript:`) — это защита от inline-инъекций.

**Остальные архитектурные ограничения** (P2, Builder неполон, `assert()`, `applySkin` мимо middleware, AINET rate limit не применяется, orphan content, `block:edit`/`filler:edit`/`skin:bundle` не реализованы, `ai_agent/` — прототип) — в `MANIFEST.md` §6–7. Прочитай их до, а не после.