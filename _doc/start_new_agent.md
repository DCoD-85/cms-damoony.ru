--- НАЧАЛО ДОКУМЕНТА ---

Start Here: Briefing для AI-агента
Точка входа для AI-агента (или нового разработчика). Прочитай целиком до любых действий.

Тебе выдаётся комплект документов. Этот файл — про окружение и правила работы. MANIFEST.md — канон: что реализовано (✅/🟡/❌), известные дефекты, баги P0/P1/P2, backlog, видение. Остальные документы (architecture.md, contracts.md, guide.md, cli.md, admin.md, ai_agent.md) — производные. Читай MANIFEST.md наравне с этим файлом.

1. Идентификация проекта
Название: Damoony Engine.
Назначение: лёгкий PHP 8.4 движок для мультидоменных сайтов с архитектурой **CLI-First** и нативной интеграцией AI-агентов.
Репозиторий: https://github.com/DCoD-85/cms-damoony.ru/
Ветка: main.
Стек: PHP 8.4, MariaDB 11.8, Apache2, Composer (PSR-4, PSR-3, PSR-11), PHPUnit 10, PHPStan level 8, Ollama (Qwen 2.5 Coder 7B для AI-агентов).

2. Характер работы
Разговорность — минимум. Отчёт по факту: что сделал, что проверено, что осталось. Без воды, без «отличная задача», без пересказа очевидного.
Не объясняй, что ты собираешься делать — делай. Если нужен план (задача большая, риск сломать) — короткий список шагов, не эссе.
Не дублируй документацию в ответах. Ссылайся на файл и раздел (MANIFEST.md §7.1), не цитируй.
Если не уверен — спроси. Одна короткая формулировка вопроса. Не «делать как в прошлый раз».
Не изобретай. Следуй правилам проекта, даже если считаешь, что «так лучше». Предложить изменение — можно, внедрять без согласования — нет.

3. Окружения
3.1. Локальное (разработка)
Железо: Intel Core i9-14900HX, 32GB RAM, Windows 11.
Веб-сервер: OSPanel 5.4.3.
Корень проекта: E:\OSPanel543\domains\damoony3.ru\
Доступ: gitbash (терминал) + VSCode.
Роль: вся разработка, тесты, PHPStan, CS-Fixer, коммиты.

Обязательные бинарники (только они):
PHP (CLI): E:\OSPanel543\modules\php\PHP_8.4\ (версия 8.4.25)
MariaDB client: E:\OSPanel543\modules\database\MariaDB-11.8-Win10\bin\ (версия 11.8)

Запрещено: PHP/MariaDB из winget, глобальный PATH, Composer из AppData. Если php --version ≠ 8.4.25 из OSPanel — исправь PATH, потом продолжай.

3.2. Production
Железо: i7, 8GB RAM, 128GB SSD + 1TB HDD.
ОС: Debian 13, Apache 2.
PHP 8.4.25, MariaDB 11.8.
Корень: /var/www/damoony.ru/.
Роль: только git pull. Прямое редактирование на сервере запрещено.

4. Git (жёсткие правила)
Единственный источник истины — GitHub main.
Цепочка: local edits → git commit → git push origin main → git pull на production.
Никаких прямых правок на production (ни SSH, ни админка, кроме контента/настроек через UI).
Не пушить без зелёных phpunit, phpstan, php-cs-fixer.
Не коммитить config/engine.config, config/site.config, config/admin.config, config/agent.config — они в .gitignore.
Регулярная синхронизация: после каждой логической порции — commit + push.

5. Разрешено и запрещено
✅ Разрешено
Создавать/редактировать файлы в src/, _dev/tests/, _doc/, lang/, stubs/, app/<domain>/....
Запускать CLI: php bin/cli.php <command> (Единственный источник истины).
Запускать тесты, PHPStan, CS-Fixer (см. §8).
Обновлять _doc/.

❌ Запрещено
Production: прямое редактирование /var/www/damoony.ru/ по SSH — только git pull.
Ядро без явного запроса владельца: src/Core/, src/Http/Kernel.php, src/App/AppFactory.php, bin/aicli.php.
Git: пуш в main при красных проверках; коммит config/*.config; изменение composer.lock без запроса.

⚠️ Текущий статус кода (см. MANIFEST.md §7): 
- Все P0 и P1 закрыты. RCE, CSRF, XSS, Path Traversal, SQL injection — устранены.
- Внедрено **Правило 3 файлов** (.php, .rules.json, .editor.json) для всех сущностей.
  Для страниц все три файла имеют единый префикс: `algoritm_{name}.{php,rules.json,editor.json}` в одной папке.
  Root алиаса: `algoritm/algoritm.{php,rules.json,editor.json}`.
- Визуальные редакторы для Страниц и Блоков **удалены** (переведены на CodeMirror). Drag&Drop остался только для Схем.
- Создан безопасный PageCompiler (JSON -> PHP) для генерации страниц AI-агентом (защита от RCE). Используется также для stub-страниц (domain:new, allias:new, structure:repair).
- Создан безопасный AI-фасад (bin/aicli.php), связанный с Ollama (Qwen 2.5 Coder 7B). Успешно генерирует 10-страничные сайты.
- **Page-слой**: PagePath, PageRules, PageNamespace — единый резолвинг путей/правил/namespace для всех page-команд.
- **Миграция**: `migrate:page-rules` — одноразовый перевод старых `.rules.json` на новую модель. Идемпотентна.
- **CLI-аудит** (24 команды): path traversal guard, карантин (`*_trash_<timestamp>/`) для destructive, idempotent remove, realDomain, scheme:copy/remove/save через CLI.

⚠️ Breaking change: `page:toggle --path` заменён на `--name` + `--parent` (root — `--name='__root__'`).

⚠️ `vars:extract` удалён (мёртвый код). Миграция старой модели — `migrate:page-rules`.

6. Ключевые пути
Что	Путь
Канон	_doc/MANIFEST.md
Ядро	src/
Домены	app/<domain>/
Страницы алиаса	app/<domain>/<allias>/algoritm/ (3 файла: algoritm_{name}.{php,rules.json,editor.json})
Root алиаса	app/<domain>/<allias>/algoritm/algoritm.{php,rules.json,editor.json}
Глобальные блоки	app/<domain>/.alliasglobal/<name>/ (3 файла)
Глобальные схемы	app/<domain>/.alliasscheme/<name>/ (3 файла)
Карантин	app/<domain>_trash_<ts>/, app/<domain>/<allias>_trash_<ts>/, .alliasscheme/<name>_trash_<ts>/, .alliasglobal/<name>_trash_<ts>/
Конфиги	config/*.config (включая agent.config для AI)
Переводы	lang/{ru,en,zh}.php
Заглушки 503	stubs/*.html
Тесты	_dev/tests/
Документация	_doc/
Runtime	var/
Миграции	migrations/*.sql
Вход сайта	www/index.php
Вход админки	www/admin/index.php
Вход CLI	bin/cli.php
Вход AI-фасада	bin/aicli.php

7. Workflow
Прочитать задачу.
Прочитать _doc/MANIFEST.md §6–7 — что реализовано, какие есть ограничения (P2), чего не делать. Это не опционально.
git status, git log --oneline -3.
Внести изменения.
Прогнать локально:

bash
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php
Если зелёное — commit + push:

bash
git add <files>
git commit -m "<scope>: <what and why>"
git push origin main
Production (только владелец):

bash
cd /var/www/damoony.ru && git pull origin main && systemctl reload apache2
Обновить _doc/, если изменилось поведение/API/конфиги.

Перед destructive-операциями (domain:remove, allias:remove, block:remove, scheme:remove, trash:clear --force, migrate:page-rules, любые правки существующих файлов) — git commit. Все destructive CLI-команды требуют --force и работают через карантин.

8. Быстрые команды
bash
# Бинарники OSPanel
which php && php --version   # 8.4.25 из E:\OSPanel543\modules\php\PHP_8.4\
mysql --version              # 11.8 из E:\OSPanel543\modules\database\MariaDB-11.8-Win10\bin\

# Если нет — в gitbash:
export PATH="/e/OSPanel543/modules/php/PHP_8.4:$PATH"
export PATH="/e/OSPanel543/modules/database/MariaDB-11.8-Win10/bin:$PATH"

# Тесты / анализ / стиль
php vendor/bin/phpunit -c _dev/phpunit.xml
php vendor/bin/phpstan analyse -c _dev/phpstan.neon --memory-limit=1G --no-progress
PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=_dev/.php-cs-fixer.php --dry-run

# Диагностика и восстановление структуры
php bin/cli.php structure:check                  # severity-aware: warning ≠ error
php bin/cli.php structure:repair --domain=example.ru

# Миграция .rules.json на новую модель (после рефакторинга)
php bin/cli.php migrate:page-rules --dry         # предпросмотр
php bin/cli.php migrate:page-rules                # боевой прогон (идемпотентно)

# Очистка карантина (4 уровня)
php bin/cli.php trash:clear                       # dry-run
php bin/cli.php trash:clear --force --older=7d    # только старше 7 дней
php bin/cli.php trash:clear --force --domain=example.ru

# Миграции БД
php bin/cli.php db:migrate

# Список всех команд
php bin/cli.php help                              # 24 команды

# Проверка AI-фасада (требуется настроенный config/agent.config)
echo '{"command": "engine_status", "args": {}}' | php bin/aicli.php