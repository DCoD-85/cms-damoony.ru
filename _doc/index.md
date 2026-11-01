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
architecture.md — жизненный цикл HTTP-запроса, слои, Low-Code-компиляция, AI-слой, AINET, media, Safe Mode, realDomain, карантин.

contracts.md — контракты (DomainResolver, Router, Region, UI, Scheme, Page, Media, Auth, I18n, Skins, компиляторы, AINET, диагностика, CLI/AI-слой).

scenarios.md — ключевые Use Cases со статусом ✅/🟡/🔴 и покрытием тестами (включая миграцию, CLI-безопасность, AINET и AI-слой).

Установка и использование
install.md — развёртывание (MariaDB, конфиги, веб-сервер, allow_php_blocks, ainet.config).

cli.md — 27 CLI-команд (включая scheme:*, media:store/remove/list, migrate:page-rules, structure:check, structure:repair, trash:clear 5 уровней), флаги, идемпотентность, path traversal guard.

admin.md — админка: вход, домены, алиасы, блоки, схемы, дерево страниц, медиа. CodeMirror и визуальный Drag&Drop (только для Схем, через CLI scheme:save). Ограничения (что только через CLI).

guide.md — руководство разработчика: Вариант A (со схемой) и Вариант B (без), правило 3 файлов, безопасное наполнение контента через JSON (page:write), картинки через image:download → media:store, миграция migrate:page-rules, path traversal и карантин CLI, чек-лист перед деплоем.

Инструкции для AI-агентов
ai_agent.md — Единая инструкция для AI. Включает: архитектуру (System), безопасную генерацию JSON-контента (Logic), сценарий «сайт с картинками» (AINET), спецификацию безопасного фасада (bin/aicli.php, 12 команд). AI не пишет сырой PHP.

Дизайн AINET
ainet.md — дизайн библиотеки AINET: зачем, архитектура (AI → AICLI → {CLI, AINET(lib)} → CLI(FS)), команды (net:status, image:search, image:download, fetch), SSRF-защита (UrlGuard, DomainWhitelist, private IP, ручные редиректы), Cache, провайдеры (wikimedia default, openverse fallback), config/ainet.config, формат ответа, что НЕ делает AINET.

Планы и Roadmap
roadmap.md — глобальные планы развития (AI, SEO, E-commerce, Enterprise). ⚠️ Планы, не реальность.

Быстрый маршрут по ролям
Роль	Читать
Новый разработчик	readme.md → MANIFEST.md → architecture.md → contracts.md → guide.md
Администратор	readme.md → admin.md → cli.md
AI-агент (Универсальный)	MANIFEST.md → ai_agent.md → cli.md → ainet.md
Интегратор AI (Python/LLM)	ai_agent.md → ainet.md → cli.md → architecture.md
Перед деплоем	MANIFEST.md §7, §10 → guide.md §14 → admin.md §«Что НЕ работает»
Перед визуальным сохранением (Схемы)	admin.md §«Визуальный редактор» + MANIFEST.md §7.1 п.4 (P0)
Восстановление структуры	cli.md (раздел structure:repair)
Миграция .rules.json	cli.md (раздел migrate:page-rules) + guide.md §13
Очистка карантина	cli.md (раздел trash:clear, 5 уровней)
Path traversal / безопасность CLI	guide.md §15 + cli.md (каждая команда)
AINET / картинки для AI	ainet.md + ai_agent.md §4 (сценарий «сайт с картинками»)
Media (www/media/)	architecture.md §«Слой медиа» + contracts.md §«Media» + cli.md (media:*)
Планирование будущего	roadmap.md

Что читать НЕ нужно (устарело)
Ранние версии документов упоминали «235 тестов», «61 тестовый файл», «295 тестов», «25 CLI-команд», «22 CLI-команды», «24 CLI-команды», «vars:extract», «100% паритет CLI/UI», «WYSIWYG», «blocks.json», «schemes.json», «визуальные редакторы страниц и блоков», «класс Algoritm» (переименован в Page), «AI пишет PHP-код» (теперь только JSON), «root rules в algoritm/.rules.json» (теперь algoritm/algoritm.rules.json), «{dir}/.rules.json» (теперь algoritm_{name}.rules.json), «scheme:* в SchemeController» (теперь через CLI), «AINET как отдельный фасад» (теперь библиотека внутри aicli), «image:download пишет напрямую из AINET» (теперь bytes → CLI media:store).
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

Изменения в текущей итерации (page-rules unify + CLI audit + AINET + media)
✅ Единая модель .rules.json: algoritm_{name}.rules.json для страниц (root — algoritm/algoritm.rules.json).
✅ Page-слой: PagePath, PageRules, PageNamespace — единый резолвинг путей/правил/namespace.
✅ BREAKING: page:toggle --path → --name + --parent (root через --name=__root__).
✅ Migrate:page-rules — одноразовая миграция старых .rules.json на новую модель. Идемпотентна.
✅ Structure:check — severity-aware (warning ≠ error, exit 0 при отсутствии errors).
✅ FileRouter — читает algoritm_{name}.rules.json на каждом шаге.
✅ Закрыт P0 в aicli: page:write теперь проверяет domain по $allowedDomains.

✅ CLI-аудит (27 команд):
- scheme:copy/scheme:remove/scheme:save — новые CLI-команды; SchemeController теперь тонкий клиент.
- Path traversal guard во всех командах с --domain/--name.
- realDomain: пути в app/ строятся по domains_real, а не по входному хосту.
- Карантин для всех destructive-команд: domain/allias/block/scheme/media/trash.
- trash:clear — обход 5 уровней (app/, app/<domain>/, .alliasscheme/, .alliasglobal/, www/media/.trash/), symlink-safe, флаги --force/--older/--domain.
- Idempotent remove: нет папки + нет в конфиге → warning, exit 0.
- Полная очистка конфига: domain:remove (16 ключей), allias:remove (8 ключей).
- filler:toggle — idempotent, проверяет блок на диске.
- db:migrate --dir — path traversal guard.
- admin:passwd / user:new — валидация (login/password/email).
- structure:repair — root через PageCompiler, algoritm.editor.json, path traversal guard.
- VarsExtractCommand — удалён (мёртвый код).
- media:store/remove/list — новые CLI-команды (25–27).

✅ Fs::rmrf — единый рекурсивный метод (Windows-safe, symlink-safe), используется в trash:clear и Cache.
✅ PHPStan/CS-Fixer расширены на bin/ и www/ (240 файлов).
✅ Тесты: 472 теста, 1157 assertions, 60 файлов.

✅ AINET + media:
- Библиотека src/Ainet/ (SSRF, HttpClient, Cache, ImageSearch, AinetFacade).
- UrlGuard: scheme/port/whitelist/private IP, ручные редиректы (CURLOPT_FOLLOWLOCATION=false).
- DomainWhitelist: точный матч, без wildcard, fail-safe (пустой = всё запрещено).
- HttpClient: GET-only, повторный UrlGuard на каждый Location.
- Cache: sha256(url) → file+meta, TTL 7 дней, Fs::rmrf.
- ImageSearch: wikimedia (default), openverse (fallback), автофоллбэк primary → next для default.
- AinetFacade: searchImages/fetchJson/fetchText/downloadImage (bytes в память, без записи).
- bin/aicli.php: whitelist 8 → 12 (net:status, image:search, image:download, fetch).
- image:download → AinetFacade (bytes) → shell_exec → cli.php media:store.
- media:store (25-я) — запись в www/media/<domain>/<allias>/<category>/<hash>.<ext> + meta.json, дедупликация.
- media:remove (26-я) — в карантин www/media/.trash/, --force.
- media:list (27-я) — список.
- bin/ainet.php — standalone-отладка (не в AI-whitelist).
- config/ainet.config.example + _doc/ainet.md — шаблон конфига и полный дизайн.
- Fallback: openverse 301 → .org, read_timeout 20s, default_provider = wikimedia.

--- КОНЕЦ ДОКУМЕНТА ---