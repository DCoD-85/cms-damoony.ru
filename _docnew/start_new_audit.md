# Start Here: Briefing для нового аудитора

Точка входа для аудитора, контрибьютора или AI-агента. Прочитай целиком до любых действий.

**Правило:** история изменений — в `git log`, не в документации. Планы — в issues. Сценарии — в `_dev/tests/`. Деривативное (список команд, метрики, флаги) — в самих командах (`php bin/cli.php help`, `composer test`). Документация описывает только актуальное «как устроено».

---

## 1. Идентификация

- **Проект:** Damoony Engine — PHP 8.4 multi-domain CMS.
- **Архитектура:** CLI-First (CLI — единственный источник истины для FS), AI-Native (aicli + 9 ролей + Navigator), Content (`/content/`, вне webroot) + Skin (`www/media/[domain]/[allias]/skin/`, git).
- **Репозиторий:** https://github.com/DCoD-85/cms-damoony.ru/
- **Ветка:** `main`.
- **Стек:** PHP 8.4, MariaDB 11.8, Apache 2, Composer (PSR-4/3/11), PHPUnit 10, PHPStan 8, PHP-CS-Fixer, Ollama (Qwen 2.5 Coder 7B), curl + openssl + gd.

---

## 2. Read-order

1. Файл `_doc/start_new_audit.md` — этот файл (окружение, правила, карта).
2. Файл `_doc/ARCH.md` — жизненный цикл, слои, инварианты, контракты, 10 принципов, P2.
3. Файл `_doc/CLI.md` — 40 команд, флаги, guards, карантин.
4. Файл `_doc/DEV.md` — how-to: страница (A/B), блок, схема, скин, контент, компиляция.
5. Файл `_doc/AI.md` — aicli (HATEOAS, роли, Navigator), AINET (SSRF, провайдеры), оркестраторы.
6. Файл `_doc/INSTALL.md` — развёртывание (только установка с нуля).
7. Файл `_doc/ADMIN.md` — UI админки (что можно, что только через CLI).
8. Файл `README.md` — обзор и быстрый старт.

---

## 3. Карта документов (границы ответственности)

| Документ | Отвечает за | Не содержит |
|---|---|---|
| `start_new_audit.md` | Вводная, окружение, правила работы, карта | Технические детали, метрики |
| `ARCH.md` | Жизненный цикл, слои, инварианты, контракты, принципы, открытые P2 | How-to, установку, историю |
| `CLI.md` | Команды: флаги, идемпотентность, per-command guards | UI, архитектуру, метрики |
| `DEV.md` | Как писать страницу/блок/схему/скин/контент | Установку, UI, per-command флаги |
| `AI.md` | aicli-контракт, роли, Navigator, AINET, оркестраторы | Установку, UI, per-command флаги |
| `INSTALL.md` | Требования, конфиги, веб-сервер, первый запуск, проверка | How-to разработчика, историю миграций |
| `ADMIN.md` | UI админки + делегирование CLI | Per-command флаги (ссылка в CLI) |
| `README.md` | Обзор, стек, быстрый старт, ссылки | Метрики (ссылка), историю |

**Правило:** один факт — одно место. Метрики, перечень команд, требования — не дублируются, только ссылки.

---

## 4. Окружение

### Локальное (разработка)

- Intel i9-14900HX, 32GB RAM, Windows 11, OSPanel 5.4.3.
- Корень: `E:\OSPanel543\domains\damoony3.ru\`. Терминал: gitbash + VSCode.
- PHP CLI: `E:\OSPanel543\modules\php\PHP_8.4\` (8.4.25).
- MariaDB client: `E:\OSPanel543\modules\database\MariaDB-11.8-Win10\bin\` (11.8).
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
- AI-контракт: `src/Ai/Roles.php`, `src/Ai/Navigator.php`, `PageCompiler`, `AinetFacade`.
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
| Оркестраторы AI | `agent_test.py` (рабочий), `ai_agent/` (будущая multi-agent модель, P3) |
| Тесты | `_dev/tests/` |
| Runtime | `var/` (+ `var/ainet-cache/`, `var/ainet-ratelimit.json`) |

---

## 8. Быстрые команды

```bash
# Бинарники (если не в PATH)
export PATH="/e/OSPanel543/modules/php/PHP_8.4:$PATH"
export PATH="/e/OSPanel543/modules/database/MariaDB-11.8-Win10/bin:$PATH"

# Проверки (источник метрик — сам вывод, не документация)
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run
# или: composer test / composer stan / composer cs / composer check

# Диагностика структуры
php bin/cli.php structure:check
php bin/cli.php structure:repair --domain=example.ru

# Список команд (40)
php bin/cli.php help

# Карантин (dry-run -> --force)
php bin/cli.php trash:clear
php bin/cli.php trash:clear --force --older=7d

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

Открытые архитектурные ограничения (P2) — в `ARCH.md`, раздел «Открытые P2». Прочитать до, а не после.