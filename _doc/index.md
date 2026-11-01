--- НАЧАЛО ДОКУМЕНТА ---

Документация Damoony Engine
Вся документация по проекту. Разделена по назначению: разработчики, администраторы, AI-агенты.

Статус проекта: 98% production-ready. 0 P0-блокеров. Полное состояние — MANIFEST.md.

⚠️ Порядок приоритета при расхождении: код в src/ → MANIFEST.md → остальные документы _doc/ → комментарии в коде. Если документ противоречит MANIFEST.md — доверяйте MANIFEST.md.

Канон
MANIFEST.md — главный документ. Возможности (✅/🟡/❌), известные дефекты (§6), баги P0/P1/P2 (§7), инварианты (§8), backlog (§10), видение (§12). Читать первым.

Общие сведения
readme.md — обзор проекта, стек, быстрый старт, честный статус, известные проблемы.

philosophy.md — 10 принципов архитектуры и степень их соблюдения.

Архитектура и контракты
architecture.md — жизненный цикл HTTP-запроса, слои, Low-Code-компиляция, AI-слой, Safe Mode, realDomain, карантин.

contracts.md — контракты (DomainResolver, Router, Region, UI, Scheme, Page, Auth, I18n, Skins, компиляторы (PageCompiler), диагностика, CLI/AI-слой).

scenarios.md — ключевые Use Cases со статусом ✅/🟡/🔴 и покрытием тестами (включая миграцию, CLI-безопасность и AI-слой).

Установка и использование
install.md — развёртывание (MariaDB, конфиги, веб-сервер, allow_php_blocks).

cli.md — 24 CLI-команды (включая scheme:new/copy/remove/save, migrate:page-rules, structure:check, structure:repair, trash:clear), флаги, идемпотентность, path traversal guard.

admin.md — админка: вход, домены, алиасы, блоки, схемы, дерево страниц. CodeMirror и визуальный Drag&Drop (только для Схем, через CLI scheme:save). Ограничения (что только через CLI).

guide.md — руководство разработчика: Вариант A (со схемой) и Вариант B (без), правило 3 файлов (единый префикс algoritm_{name}.{php,rules.json,editor.json}), безопасное наполнение контента через JSON (page:write), миграция migrate:page-rules, path traversal и карантин CLI, чек-лист перед деплоем.

Инструкции для AI-агентов
ai_agent.md — Единая инструкция для AI. Включает в себя: архитектуру (System), безопасную генерацию JSON-контента (Logic) и спецификацию безопасного фасада (bin/aicli.php). AI не пишет сырой PHP.

Планы и Roadmap
roadmap.md — глобальные планы развития (AI, SEO, E-commerce, Enterprise). ⚠️ Планы, не реальность.

Быстрый маршрут по ролям
Роль	Читать
Новый разработчик	readme.md → MANIFEST.md → architecture.md → contracts.md → guide.md
Администратор	readme.md → admin.md → cli.md
AI-агент (Универсальный)	MANIFEST.md → ai_agent.md → cli.md
Интегратор AI (Python/LLM)	ai_agent.md → cli.md → architecture.md
Перед деплоем	MANIFEST.md §7, §10 → guide.md §14 → admin.md §«Что НЕ работает»
Перед визуальным сохранением (Схемы)	admin.md §«Визуальный редактор» + MANIFEST.md §7.1 п.4 (P0)
Восстановление структуры	cli.md (раздел structure:repair)
Миграция .rules.json	cli.md (раздел migrate:page-rules) + guide.md §13
Очистка карантина	cli.md (раздел trash:clear, 4 уровня)
Path traversal / безопасность CLI	guide.md §15 + cli.md (каждая команда)
Планирование будущего	roadmap.md

Что читать НЕ нужно (устарело)
Ранние версии документов упоминали «235 тестов», «61 тестовый файл», «295 тестов», «25 CLI-команд», «22 CLI-команды», «vars:extract», «100% паритет CLI/UI», «WYSIWYG», «blocks.json», «schemes.json», «визуальные редакторы страниц и блоков», «класс Algoritm» (переименован в Page), «AI пишет PHP-код» (теперь только JSON), «root rules в algoritm/.rules.json» (теперь algoritm/algoritm.rules.json), «{dir}/.rules.json» (теперь algoritm_{name}.rules.json), «scheme:copy/remove/save в SchemeController» (теперь через CLI).
Все эти утверждения исправлены в текущих версиях. Если встретите старую формулировку — доверяйте MANIFEST.md.

Ключевые P0 (для быстрой ориентации)
~~CSRF на destructive GET.~~ ✅ Закрыто (stage-2b).
~~addslashes в компиляторах.~~ ✅ Закрыто (stage-1).
~~Response::internalError($e->getMessage()) в prod.~~ ✅ Закрыто (stage-3).
~~Два формата .edit.json без миграции.~~ ✅ Закрыто (stage-2a).
~~SchemeCompiler::str_replace('array (', '[').~~ ✅ Закрыто (stage-1).
~~Порт в Host не нормализуется.~~ ✅ Закрыто (stage-3).
~~RCE через custom/if блоки в визуальном редакторе.~~ ✅ Закрыто (удалены визуальные редакторы, добавлен флаг allow_php_blocks).

Все P0 закрыты. Подробнее — MANIFEST.md §7.1 и §10.

Изменения в текущей итерации (page-rules unify + CLI audit)
✅ Единая модель .rules.json: algoritm_{name}.rules.json для страниц (root — algoritm/algoritm.rules.json).
✅ Page-слой: PagePath, PageRules, PageNamespace — единый резолвинг путей/правил/namespace.
✅ BREAKING: page:toggle --path → --name + --parent (root через --name=__root__).
✅ Migrate:page-rules — одноразовая миграция старых .rules.json на новую модель. Идемпотентна.
✅ Structure:check — severity-aware (warning ≠ error, exit 0 при отсутствии errors).
✅ FileRouter — читает algoritm_{name}.rules.json на каждом шаге.
✅ Закрыт P0 в aicli: page:write теперь проверяет domain по $allowedDomains.

✅ CLI-аудит (24 команды):
- scheme:copy/scheme:remove/scheme:save — новые CLI-команды; SchemeController теперь тонкий клиент над CLI.
- Path traversal guard во всех командах с --domain/--name (str_contains('..') + regex).
- realDomain: пути в app/ строятся по domains_real, а не по входному хосту (block/filler/scheme).
- Карантин (`*_trash_<timestamp>/`) для всех destructive-команд: domain:remove, allias:remove, block:remove, scheme:remove, trash:clear.
- trash:clear — обход 4 уровней карантина (app/, app/<domain>/, .alliasscheme/, .alliasglobal/), symlink-safe, флаги --force/--older/--domain.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- Полная очистка конфига: domain:remove (16 ключей), allias:remove (8 ключей).
- filler:toggle — idempotent (не перезаписывает конфиг при том же значении), проверяет блок на диске.
- db:migrate --dir — path traversal guard (только подпапки migrations/).
- admin:passwd — валидация login (3-32 [a-zA-Z0-9_.-]) + password ≥8.
- user:new — валидация email, уникальность login и email, password ≥8.
- structure:repair — root через PageCompiler (было битый PHP), algoritm.editor.json (правило 3 файлов), path traversal guard.
- VarsExtractCommand — удалён (мёртвый код), тест удалён.

✅ PHPStan/CS-Fixer расширены на bin/ и www/ (204 файла).
✅ Тесты: 336 тестов, 798 assertions, 60 файлов.

--- КОНЕЦ ДОКУМЕНТА ---