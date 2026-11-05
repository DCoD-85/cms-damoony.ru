# Start Here: Briefing для нового аудитора

Точка входа для аудитора, контрибьютора или AI-агента. Прочитай целиком до любых действий.

**Правило:** история изменений — в `git log`, не в документации. Планы — в issues. Сценарии — в `_dev/tests/`. Деривативное (список команд, метрики, флаги) — в самих командах (`php bin/cli.php help`, `composer test`). Документация описывает только актуальное «как устроено».

---

## 1. Идентификация

- **Проект:** Damoony Engine — PHP 8.4 multi-domain CMS.
- **Версия:** v2.0.0 (Production Ready + Multi-Agent AI + Markdown Parser).
- **Архитектура:** CLI-First (CLI — единственный источник истины для FS), AI-Native (aicli + 9 ролей + Navigator + `ai_agent/brain.py`), Content (`/content/`, вне webroot) + Skin (`www/media/[domain]/[allias]/skin/`, git).
- **Репозиторий:** https://github.com/DCoD-85/cms-damoony.ru/
- **Ветка:** `main`.
- **Стек:** PHP 8.4, MariaDB 11.8, Apache 2, Composer (PSR-4/3/11), PHPUnit 10, PHPStan 8, PHP-CS-Fixer, Python 3 (для `ai_agent/brain.py`), Ollama (Qwen 2.5 Coder 7B / Gemma 4), curl + openssl + gd.

---

## 2. Read-order

1. `_doc/start_new_audit.md` — этот файл (окружение, правила, карта).
2. `_doc/arch.md` — жизненный цикл, слои, инварианты, контракты, 10 принципов, P2.
3. `_doc/cli.md` — команды, флаги, guards, карантин (актуальный состав — `php bin/cli.php help`).
4. `_doc/dev.md` — how-to: страница (A/B), блок, схема, скин, контент, компиляция.
5. `_doc/ai.md` — aicli (HATEOAS, роли, Navigator), AINET (SSRF, провайдеры), мультиагентная генерация (`brain.py`), Markdown-парсер и валидатор.
6. `_doc/aiui.md` — AIUI (AI-редактор страниц), ai:edit-page, BlockCodegen.
7. `_doc/install.md` — развёртывание (только установка с нуля).
8. `_doc/admin.md` — UI админки (что можно, что только через CLI).

---

## 3. Карта документов (границы ответственности)

| Документ | Отвечает за | Не содержит |
|---|---|---|
| `start_new_audit.md` | Вводная, окружение, правила работы, карта | Технические детали, метрики |
| `arch.md` | Жизненный цикл, слои, инварианты, контракты, принципы, открытые P2 | How-to, установку, историю |
| `cli.md` | Команды: флаги, идемпотентность, per-command guards | UI, архитектуру, метрики |
| `dev.md` | Как писать страницу/блок/схему/скин/контент | Установку, UI, per-command флаги |
| `ai.md` | aicli-контракт, роли, Navigator, AINET, мультиагентная генерация | Установку, UI, per-command флаги |
| `aiui.md` | AIUI (чат + превью), ai:edit-page, BlockCodegen | Внутренности LLM, установку |
| `install.md` | Требования, конфиги, веб-сервер, первый запуск, проверка | How-to разработчика, историю миграций |
| `admin.md` | UI админки + делегирование CLI | Per-command флаги (ссылка в CLI) |
| `README.md` (корень) | Обзор, стек, быстрый старт, ссылки | Метрики (ссылка), историю |

**Правило:** один факт — одно место. Метрики, перечень команд, требования — не дублируются, только ссылки.

---

## 4. Окружение

### Локальное (разработка)

- Intel i9-14900HX, 32GB RAM, Windows 11, Локальный стек (Nginx + PHP-CGI + MariaDB).
- Корень: `E:\localserver\domains\damoony3.ru\`. Терминал: gitbash + VSCode.
- PHP CLI: `E:\localserver\tools\php\` (8.4.25).
- MariaDB client: `E:\localserver\tools\mariadb\bin\` (11.8).
- Расширения PHP: `curl`, `gd`, `openssl`, `mbstring`, `pdo_mysql`, `pdo_sqlite`, `fileinfo`, `tokenizer`, `xml`.
- **Запрещено:** PHP/MariaDB из winget, глобальный PATH, Composer из AppData.

### Production

- i7, 8GB RAM, 128GB SSD + 1TB HDD, Debian 13, Apache 2, PHP 8.4.25, MariaDB 11.8.
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
- Ядро: `src/Core/`, `src/Http/Kernel.php`, `src/App/AppFactory.php`, `bin/aicli.php`, `bin/ainet.php`.
- AI-контракт: `src/Ai/Roles.php`, `src/Ai/Navigator.php`, `PageCompiler`, `BlockCompiler`, `AinetFacade`.
- Роутинг: `PagePath`, `PageRules`, `FileRouter`.
- Безопасность: механизм карантина, path traversal guard, SSRF (UrlGuard), `www/content.php`/`ContentResponder`, `skin:edit` whitelist.
- Git: push в `main` при красных проверках; commit `config/*.config`; изменение `composer.lock`.

---

## 7. Ключевые пути

| Что | Путь |
|---|---|
| Ядро | `src/` |
| AINET (библиотека) | `src/Ainet/` |
| AI (Roles, Navigator) | `src/Ai/` |
| Content responder | `src/Content/`, `www/content.php` |
| Домен | `app/[domain]/` |
| Страница | `app/[domain]/[allias]/algoritm/algoritm_[name].{php,rules.json,editor.json}` |
| Root алиаса | `app/[domain]/[allias]/algoritm/algoritm.{php,rules.json,editor.json}` |
| Блоки | `app/[domain]/.alliasglobal/[name]/` |
| Схемы | `app/[domain]/.alliasscheme/[name]/` |
| Content (runtime) | `/content/[domain]/[allias]/[category]/[hash].[ext]` + `[hash].meta.json` |
| Skin (git) | `www/media/[domain]/[allias]/skin/[name]/{css,js,img,skin.json}` |
| Карантин (6 уровней) | `app/[d]_trash_[ts]/`, `app/[d]/[a]_trash_[ts]/`, `.alliasscheme/[n]_trash_[ts]/`, `.alliasglobal/[n]_trash_[ts]/`, `/content/.trash/...`, `www/media/.trash/...` |
| Конфиги | `config/*.config` (`engine`, `site`, `admin`, `agent`, `ainet`) |
| Точки входа | `www/index.php` (сайт), `www/admin/index.php` (админка), `www/content.php` (content), `bin/cli.php` (CLI), `bin/aicli.php` (AI, единственная), `bin/ainet.php` (standalone-отладка, не для AI) |
| AI-Оркестраторы | `ai_agent/brain.py` (рабочий мультиагент) |
| Тесты | `_dev/tests/` |
| Runtime | `var/` (+ `var/ainet-cache/`, `var/ainet-ratelimit.json`) |

---

## 8. Быстрые команды

```bash
# Бинарники (если не в PATH)
export PATH="/e/localserver/modules/php/PHP_8.4:$PATH"
export PATH="/e/localserver/modules/database/MariaDB-11.8-Win10/bin:$PATH"

# Проверки (источник метрик — сам вывод, не документация)
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run
# или: composer test / composer stan / composer cs / composer check

# Диагностика структуры
php bin/cli.php structure:check
php bin/cli.php structure:repair --domain=example.ru

# Список команд (`php bin/cli.php help`)
php bin/cli.php help

# Карантин (dry-run -> --force)
php bin/cli.php trash:clear
php bin/cli.php trash:clear --force --older=7d

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

### 10.5. Пошаговая работа

Не пиши код для 10 файлов за раз. Один шаг → один файл (или одна логическая правка) → владелец применяет → прогоняет тесты → присылает результат → только потом следующий шаг.

### 10.6. Тестирование после каждого шага

После каждого логического изменения владелец запускает:

* `php vendor/bin/phpunit -c _dev/phpunit.xml`
* `php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress`
* `PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run --diff`

Жди отчёт (зелёные тесты или ошибки) и только потом иди дальше. Красные проверки блокируют push.

### 10.7. Текущая фаза (v2.0.0 → аудит)

В отличие от ранних итераций (где фокус был на Wizard/State Machine, реализованном в `7e3975c` + `5c5af6a`), текущая работа — **оптимизация и консолидация**:

1. Phase 2 — ✅ чистка мусора (P0).
2. Phase 3 — консолидация документации (12 → 8 файлов + `README.md`).
3. Phase 4 — качество тестов (skip → `#[RequiresPhpExtension]`, `assertFileExists` вместо `markTestSkipped`).
4. Phase 5 — единые точки входа (`www/index.php`, `www/admin/index.php`, `bin/cli.php`, `bin/aicli.php`).
5. Phase 6 — упрощение кода (по одному файлу за шаг).

Актуальный roadmap v3.0.0 — в `_doc/roadmap.md`. Открытые P2 — в `_doc/arch.md`, раздел «Открытые P2».

