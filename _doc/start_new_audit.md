# Start Here: Briefing для нового аудитора

Точка входа для аудитора, контрибьютора или AI-агента. Прочитай целиком до любых действий.

**Правило:** история изменений — в `git log`, не в документации. Планы — в issues. Сценарии — в `_dev/tests/`. Деривативное (список команд, метрики, флаги) — в самих командах (`php bin/cli.php help`, `composer test`). Документация описывает только актуальное «как устроено».

---

## 1. Идентификация

- **Проект:** Damoony Engine — PHP 8.5 multi-domain CMS.
- **Версия:** v3.1-dev (Multi-Agent Orchestrator + AIUI Phase B).
- **Архитектура:** CLI-First (CLI — единственный источник истины для FS), AI-Native:
  - **Внутренний Orchestrator** (`src/Ai/Orchestrator/`) — мультиагентный конвейер для AIUI: Interviewer → Architect → Coder, Blackboard в `context.json`, per-page генерация.
  - **Внешний aicli** (`bin/aicli.php`) — JSON-in/out для `brain.py` и сторонних AI-агентов, 9 ролей.
  - **Content** (`/content/`, вне webroot) + **Skin** (`www/media/[domain]/[allias]/skin/`, git).
- **Репозиторий:** https://github.com/DCoD-85/cms-damoony.ru/
- **Ветка:** `main`.
- **Стек (dev):** PHP 8.5 (NTS), MariaDB 11.8, **Nginx + PHP-CGI** (standalone `E:\localserver`, не OSPanel), Composer (PSR-4/3/11), PHPUnit 11, PHPStan 8, PHP-CS-Fixer, Python 3 (`ai_agent/brain.py`), Ollama (`gemma4:e4b` для чата, `deepseek-coder:6.7b` для кода/JSON).
- **Стек (prod):** Debian 13, PHP 8.5 + PHP-FPM, Nginx, MariaDB 11.8.

---

## 2. Read-order

См. §3 (карта документов) — она полнее и содержит границы ответственности. Порядок чтения: §3 сверху вниз.

## 3. Карта документов (границы ответственности)

| Документ | Отвечает за | Не содержит |
|---|---|---|
| `start_new_audit.md` | Вводная, окружение, правила работы, карта | Технические детали, метрики |
| `arch.md` | Жизненный цикл, слои, инварианты, контракты, принципы, открытые P2 | How-to, установку, историю |
| `cli.md` | Команды: флаги, идемпотентность, per-command guards | UI, архитектуру, метрики |
| `dev.md` | Как писать страницу/блок/схему/скин/контент | Установку, UI, per-command флаги |
| `ai.md` | aicli-контракт, роли, Navigator, AINET, мультиагентная генерация | Установку, UI, per-command флаги |
| `aiui.md` | AIUI (чат + превью), ai:edit-page, BlockCodegen | Внутренности LLM, установку |
| `orchestrator.md` | Внутренний мультиагентный конвейер AIUI (Blackboard, State Machine, ModelRouter) | Внешний aicli, brain.py |
| `install.md` | Требования, конфиги, веб-сервер, первый запуск, проверка | How-to разработчика, историю миграций |
| `admin.md` | UI админки + делегирование CLI | Per-command флаги (ссылка в CLI) |
| `progressive_enhancement.md` | AJAX-логика app.js, Toasts и MinStruct | Установку, UI-дизайн |
| `visuo_showcase.md` | Эталонный пример мультиязычного сайта на движке | Архитектуру ядра, установку |
| `dbcli.md` | Декларативная работа с БД (JSON-схемы, генерация SQL и PHP) | SQL-инъекции, ручное написание миграций |
| `README.md` (корень) | Обзор, стек, быстрый старт, ссылки | Метрики (ссылка), историю |

**Правило:** один факт — одно место. Метрики, перечень команд, требования — не дублируются, только ссылки.

---

## 4. Окружение

### Локальное (разработка)

- Intel i9-14900HX, 32GB RAM, Windows 11, Локальный стек (Nginx + PHP-CGI + MariaDB).
- Корень: `E:\localserver\domains\damoony3.ru\`. Терминал: gitbash + VSCode.
- PHP CLI: `E:\localserver\tools\php8.5.12\` (8.5.12).
- MariaDB client: `E:\localserver\tools\mariadb\bin\` (11.8).
- Расширения PHP: `curl`, `gd`, `openssl`, `mbstring`, `pdo_mysql`, `pdo_sqlite`, `fileinfo`, `tokenizer`, `xml`.
- **Запрещено:** PHP/MariaDB из winget, глобальный PATH, Composer из AppData.

#### Запуск CLI (Windows standalone)

Системный `php` в терминале может указывать на старую версию без нужных расширений (например, без `curl`). **Всегда** запускай CLI через наш бинарник с явным конфигом:

    "/e/localserver/tools/php8.5.12/php.exe" -c "/e/localserver/tools/php8.5.12/php.ini" bin/cli.php [команда]

Для удобства — алиас в `~/.bashrc` (с `-c`, чтобы совпадало с командой выше):

    alias php='/e/localserver/tools/php8.5.12/php.exe -c /e/localserver/tools/php8.5.12/php.ini'

#### Управление сервером

Трей-иконка **LocalDamoony** (в системном трее):
- Правый клик -> Запустить / Остановить / Перезапустить
- Автозагрузка при входе в Windows
- Файлы: `E:/localserver/server.ps1`, `server.vbs`
- Логи: `E:/localserver/logs/` (nginx, mariadb, php)

Если PHP-CGI упал или завис — через трей `Остановить` -> `Запустить` (сбросит кэш opcache и перечитает php.ini).

### Production

- i7, 8GB RAM, 128GB SSD + 1TB HDD, Debian 13, Nginx, PHP 8.5, MariaDB 11.8.
- Корень: `/var/www/damoony.ru/`. Только `git pull`. Прямое редактирование запрещено.

---

## 5. Git-правила

- Единственный источник истины — GitHub `main`.
- Цепочка: local -> `git commit` -> `git push origin main` -> `git pull` на production.
- Не пушить при красных `phpunit` / `phpstan` / `php-cs-fixer`.
- Не коммитить `config/*.config` (в `.gitignore`), `/content/`, `app/damoony3test.ru/`.
- После каждой логической порции — commit + push.

---

## 6. Разрешено / запрещено

**Разрешено:** править `src/`, `_dev/tests/`, `_doc/`, `lang/`, `stubs/`, `app/[domain]/`; запускать CLI (`php bin/cli.php [cmd]`) и проверки (`composer test/stan/cs`).

**Запрещено без явного запроса владельца:**

- Production: прямое редактирование `/var/www/damoony.ru/`.
- Ядро: `src/Core/`, `src/Core/Http/Kernel.php`, `src/Core/App/AppFactory.php`, `bin/aicli.php`, `bin/ainet.php`, `bin/dbcli.php`.
- AI-контракт: `src/Ai/Roles.php`, `src/Ai/Navigator.php`, `src/UI/Page/PageCompiler.php`, `src/UI/Region/BlockCompiler.php`, `src/Ainet/AinetFacade.php`.
- Роутинг: `src/UI/Page/PagePath.php`, `src/UI/Page/PageRules.php`, `src/Core/Routing/FileRouter.php`.
- Безопасность: механизм карантина, path traversal guard, SSRF (UrlGuard), `www/content.php`/`src/Core/Content/ContentResponder.php`, `skin:edit` whitelist.
- Git: push в `main` при красных проверках; commit `config/*.config`; изменение `composer.lock`.

---

## 7. Ключевые пути

| Что | Путь |
|---|---|
| Ядро | `src/Core/` (App, Http, Routing, Domain, I18n, Security, Session) |
| UI (Верстка) | `src/UI/` (Element, Page, Region, Scheme, Skin, Components) |
| Data (БД) | `src/Data/` (Db, DataProvider, Auth) |
| CLI | `src/Cli/` (Commands, Db, Ai) |
| AINET (библиотека) | `src/Ainet/` |
| AI (Roles, Navigator) | `src/Ai/` |
| **AI Orchestrator (внутренний)** | `src/Ai/Orchestrator/` (Blackboard, State Machine) |
| **AI Agents** | `src/Ai/Orchestrator/Agent/` (Interviewer, Architect, Coder) |
| **Model Router** | `src/Ai/ModelRouter.php` + `config/models.config` |
| Content responder | `src/Core/Content/`, `www/content.php` |
| Домен | `app/[domain]/` |
| Страница | `app/[domain]/[allias]/algoritm/algoritm_[name].{php,rules.json,editor.json}` |
| Root алиаса | `app/[domain]/[allias]/algoritm/algoritm.{php,rules.json,editor.json}` |
| Блоки | `app/[domain]/.blocksglobal/[name]/` |
| Схемы | `app/[domain]/.schemesglobal/[name]/` |
| Content (runtime) | `/content/[domain]/[allias]/[category]/[hash].[ext]` + `[hash].meta.json` |
| Skin (git) | `www/media/[domain]/[allias]/skin/[name]/{css,js,img,skin.json}` |
| Карантин (6 уровней) | `app/[d]_trash_[ts]/`, `app/[d]/[a]_trash_[ts]/`, `.schemesglobal/[n]_trash_[ts]/`, `.blocksglobal/[n]_trash_[ts]/`, `/content/.trash/...`, `www/media/.trash/...` |
| Конфиги | `config/*.config` (`engine`, `site`, `admin`, `agent`, `ainet`) |
| Точки входа | `www/index.php` (сайт), `www/admin/index.php` (админка), `www/content.php` (content), `bin/cli.php` (CLI), `bin/dbcli.php` (работа с БД), `bin/aicli.php` (AI), `bin/ainet.php` (AINET) |
| AI-Оркестраторы | `ai_agent/brain.py` (рабочий мультиагент) |
| Тесты | `_dev/tests/` |
| Runtime | `var/` (+ `var/ainet-cache/`, `var/ainet-ratelimit.json`) |
| **Локальный сервер (Windows)** | `E:/localserver/` (tools: nginx/php/mariadb, domains: проекты) |
| **Трей-менеджер** | `E:/localserver/server.ps1` + `server.vbs` |
| **Session (AIUI)** | `app/.sess_{tab}.{domain}/` (изолированная копия домена) |
| **Session media** | `www/media/.sess_{tab}.{domain}/` (изолированный скин) |

---

## 8. Быстрые команды

```bash
# Бинарники (Windows standalone)
# Используй полный путь с явным php.ini (иначе системный php без cURL)
PHP="/e/localserver/tools/php8.5.12/php.exe -c /e/localserver/tools/php8.5.12/php.ini"
NGINX="/e/localserver/tools/nginx/nginx.exe"
MYSQL="/e/localserver/tools/mariadb/bin/mariadb.exe"

# Проверки (источник метрик — сам вывод, не документация)
"$PHP" vendor/bin/phpunit -c _dev/phpunit.xml
"$PHP" vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run
# или: composer test / composer stan / composer cs / composer check

# Диагностика структуры
php bin/cli.php structure:check
php bin/cli.php structure:repair --domain=example.ru

# Список команд (`php bin/cli.php help`)
php bin/cli.php help

# Карантин (dry-run -> --force)
php bin/cli.php trash:clear
php bin/cli.php trash:clear --force --older=7d

# Работа с БД (dbcli)
php bin/dbcli.php db:schema:init --domain=example.ru --table=products
php bin/dbcli.php db:schema:generate-sql --domain=example.ru

# Запуск мультиагента (E2E генерация сайта)
python -m ai_agent.brain <domain> <allias> "<brief>" <model_name>
# Например:
python -m ai_agent.brain damoony3test.ru coffee "Сделай сайт приюта для собак с разделом о нас" qwen2.5-coder:7b

# Проверка AI-слоя (нужны config/agent.config + config/ainet.config)
echo '{"command": "ai:start", "role": "architect"}' | php bin/aicli.php
echo '{"command": "net:status", "args": {}}'        | php bin/aicli.php
```

---

## 9. Эскалация — остановиться и спросить владельца

- Задача неоднозначна или риск сломать production.
- Нужны права (SSH, DB root).
- Требуется трогать ядро, AI-контракт, роутинг, безопасность (см. §6).
- Destructive-операция без предварительного `git commit`.
- Изменение модели `.rules.json`, механизма карантина, path traversal guard, SSRF-защиты.
- Изменение структуры `/content/` или `www/media/` (три зоны + скин).

Открытые архитектурные ограничения (P2) — в `_doc/arch.md`, раздел «Открытые P2». Прочитать до, а не после.

---

## 10. Правила работы AI-аудитора (обязательно к исполнению)

Применимо к AI-агенту, работающему над проектом через диалог с владельцем.

### 10.1. Только факты, никаких догадок

AI не видит файловую систему напрямую. Никогда не придумывай содержимое файлов, имена классов, названия методов или аргументы команд. Если нужен код — запроси его у владельца.

### 10.2. Как запрашивать код

Используй команды терминала (GitBash), которые владелец выполнит и пришлёт вывод:

* `cat path/to/file.php`
* `grep -rn "ClassName" src/`
* `php bin/cli.php help`
* `find app -type d`

### 10.3. Запрос оригиналов перед изменением

Прежде чем предлагать изменения в файле, **всегда** проси показать его текущее состояние (`cat` или `git diff`), чтобы не перезаписать свежие правки.

### 10.4. Формат выдачи кода

* **Приоритет 1:** полный листинг файла (от `<?php` до `}`), обёрнутый 4 обратными кавычками (````), чтобы не ломать Markdown.
* **Приоритет 2 (если файл огромный):** точный патч «Найти → Заменить» с конкретными строками. Никаких «добавь метод где-то там».
* **Приоритет 3 (массовые правки по шаблону):** python-скрипт с `assert` на каждый паттерн, чтобы при несовпадении файл не трогался.
* **Маркеры в скриптах-патчах:** брать дословно из `grep "^## " _doc/<file>` или `cat -A`. Не переводить, не сокращать, не полагаться на память. Пример ошибки: `## 9. Local server` (мой маркер) vs `## 9. Локальный сервер (Windows standalone)` (реальный) — скрипт не тронул файл (assert сработал).
* **Применение python-скриптов:** через `cat > path << 'PYEOF' ... PYEOF` (literal heredoc, кавычки обязательны), затем `python path`. Не вставлять содержимое скрипта прямо в терминал — bash выполнит его как shell-команды и создаст мусорные файлы (пример: `?? =` после неудачной вставки).

### 10.5. Пошаговая работа

Не пиши код для 10 файлов за раз. Один шаг → один файл (или одна логическая правка) → владелец применяет → прогоняет тесты → присылает результат → только потом следующий шаг.

### 10.6. Тестирование после каждого шага

После каждого логического изменения владелец запускает:

* `php vendor/bin/phpunit -c _dev/phpunit.xml`
* `php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress`
* `PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run --diff`

Жди отчёт (зелёные тесты или ошибки) и только потом иди дальше. Красные проверки блокируют push.

### 10.7. Текущая фаза (v3.1-dev)

Стратегия: **Tools-First**. AI — ассистент, не генератор. Основной продукт работает без AI (IDE-подсказки, JSON-редактор, DataProvider). AI остаётся опциональным (wizard, bulk, review).

**Закрытые фазы:**

- **Фаза A** (изоляция FS): `--session` для `page:new`/`page:write`, `SchemeLoader` session-aware, `SessionStartCommand` копирует `www/media/` + `.schemesglobal/` + пишет `permissions` в `.session.json`. См. коммиты `1916b46`, `23d1f9e`.
- **Фаза B** (Multi-Agent Orchestrator): Blackboard (`context.json`), Interviewer -> Architect -> Coder, `ModelRouter` (`config/models.config`), AIUI (`/admin/aiui.php`, 4 action). См. `_doc/orchestrator.md`, `_doc/aiui.md`, коммиты `441e060`, `b7500ef`.

**Закрытые фазы:**

- **Фаза E** (DB CLI): Инструмент `bin/dbcli.php` — декларативное управление БД через JSON-схемы. Генерация SQL-миграций и PHP-провайдеров. См. `_doc/dbcli.md`.
- **Фаза F** (Visual DB Admin): Визуальный конструктор таблиц в админке (`/admin/{lang}/domains/{domain}/db`). Создание таблиц и колонок через UI, которое вызывает `dbcli` под капотом.

**Текущая работа:**

- **Фаза G** (CRUD Records): Добавление кнопок «Создать запись», «Редактировать», «Удалить» для записей в таблицах через UI админки.

**Следующие фазы:**

- **Фаза C** (Tools-First IDE): `api:reflect` (reflection Builder/Element/Attrs/Text/RegionContext -> `api.json`), CodeMirror hint, snippets, детерминированный Linter. **Не AI.** См. план в `_doc/dev.md` §10.

**Открытые P2:** `_doc/arch.md` §6 (15 пунктов).

