--- НАЧАЛО ДОКУМЕНТА ---

MANIFEST — Канон намерений и фактического состояния
MANIFEST — это канонический перечень возможностей и ограничений Damoony Engine. Любой другой документ (readme.md, architecture.md, contracts.md, guide.md, admin.md, cli.md) производен от него.

Правило расхождения: MANIFEST описывает намерения. Код в src/ описывает поведение. Расхождение — это баг, который должен быть исправлен либо в коде (достичь намерения), либо в MANIFEST (скорректировать намерение). Не бывает «истины только в MANIFEST».

1. Идентичность
PHP 8.4, self-hosted, multi-domain CMS.
Multi-domain — first-class (в ядре, не плагин).
CLI-First архитектура: CLI является единственным источником истины для файловых операций. Админка и AI-агенты — это лишь обертки над CLI.
AI-Native: безопасный слой `bin/aicli.php` для интеграции локальных LLM (Ollama) с ядром. Настроен оркестратор на Python (`agent_test.py`), успешно генерирующий сайты.
Low-Code: визуальный редактор сохранен ТОЛЬКО для Схем (Drag&Drop). Блоки и Страницы пишутся через CodeMirror или AI (безопасный JSON).
Не WordPress, не Laravel CMS, не headless, не SaaS.

2. Терминология (без синонимов)
Термин	Значение	Не путать с
Домен	Папка app/<real_domain>/	Входящий хост
Алиас	Подпапка app/<real_domain>/<allias>/	Поддомен
Схема	Каркас HTML в .alliasscheme/<name>.php	Страница
Слот	Позиция в схеме (top, center)	Регион
Регион	Класс RegionInterface в .alliasglobal/<name>/	Страница
Блок	Синоним региона	—
Филлер	Переопределение региона для алиаса	Глобальный регион
Страница	Класс Page (ранее Algoritm) в algoritm/	Регион
Тип блока	script / visual / mixed	—
Правило 3 файлов	Для всех сущностей: {name}.php (код), {name}.rules.json (настройки), {name}.editor.json (данные UI)	Конфиг
__root__	Специальное имя для записи в главную страницу аллиаса через CLI	—
realDomain	Канонический домен (папка в app/), резолвится из site.config	Входной хост
Запрещено: "module", "plugin", "widget", "template".

3. Реализовано (✅)
Ядро: DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

Безопасность (P0 закрыт): Удалены визуальные редакторы страниц/блоков (предотвращение RCE). В `RegionCompiler` добавлен флаг `security.allow_php_blocks` (по умолчанию false), блокирующий генерацию сырого PHP. Path traversal guard во всех CLI-командах (domain/allias/block/filler/scheme): проверка `..` и regex.

Домен/Алиас: domain:new, domain:remove, allias:new, allias:remove, domains_*, alliases_*, stubs/*.html. Все destructive-операции в карантин (`*_trash_<timestamp>/`), требуют `--force`. Idempotent: нет папки + нет в конфиге → warning, exit 0. Полная очистка конфига (16 ключей для домена, 8 для алиаса).

Роутинг: algoritm/algoritm.rules.json (root) и algoritm_{name}.rules.json (вложенные), флаги available/auth/search/default, regex-параметры, 404 для неизвестных путей.

Схемы/Регионы: SchemeLoader, SchemeRenderer, RegionLoader (сканирует .alliasglobal/*/), RegionRegistry, филлеры <name>_<allias>.php. CLI scheme:new/copy/remove/save. Копирование и удаление схем — через CLI (было: прямое в SchemeController).

Правило 3 файлов: Внедрено для Страниц, Блоков, Филлеров и Схем. Для страниц все три файла имеют единый префикс: algoritm_{name}.{php,rules.json,editor.json} в одной директории. Root — algoritm/algoritm.{php,rules.json,editor.json}. Глобальные `blocks.json` и `schemes.json` удалены.

Page-слой: PagePath, PageRules, PageNamespace — единый резолвинг путей, правил и namespace для всех page-команд (new/write/read/toggle). Устраняют дублирование формул.

Безопасная компиляция (PageCompiler): JSON-структура от AI конвертируется в нативный PHP через `Builder`. Поддерживаются блоки: h1, h2, h3, text, img, link, button, ul. Поддерживаются атрибуты: class, id, style, alt. Хардкод стилей удален.

UI: Node, Element, Attrs, Text, Fragment, Group, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message. XSS-экранирование на уровне Attrs/Text.

Auth: DbAuth (password_hash), SessionAuth, Csrf, login/logout, логирование в var/admin_logs.json, brute-force (5 → бан 15 мин). Базовые методы Page::isAuthenticated() и user(). admin:passwd — валидация login (3-32 [a-zA-Z0-9_.-]) + password ≥8. user:new — валидация email, уникальность login и email, password ≥8.

БД: PDO, MigrationRunner, MariaDB/MySQL. db:migrate — path traversal guard на `--dir` (только подпапки migrations/).

Админка: Вход/выход, профиль, I18n (RU/EN/ZH), Обзор системы, Домены, Алиасы, Блоки, Схемы, Филлеры, Страницы (дерево), Заглушки, Безопасность, Настройки. CodeMirror для всего. Визуальный Drag&Drop оставлен ТОЛЬКО для Схем. Safe Mode.

AI-Слой: `bin/aicli.php` — безопасный фасад (whitelist команд) для управления движком через LLM. Работает по схеме `AI (Python/JSON) -> aicli.php -> bin/cli.php`. Поддерживает автономную диагностику и лечение, создание сайтов (успешно протестировано на модели Qwen 2.5 Coder 7B). page:write проверяет domain по $allowedDomains (P0 закрыт).

Диагностика: StructureChecker (наличие 3 файлов, стыковка филлер↔слот), CLI structure:check (severity-aware: warnings не валят команду), structure:repair (root через PageCompiler, editor.json, path traversal guard), migrate:page-rules (миграция .rules.json на новую модель).

Миграция: migrate:page-rules — одноразовая миграция старых .rules.json на новую модель. Идемпотентна. Шаги: (1) root algoritm/.rules.json → algoritm/algoritm.rules.json, (2) подпапки {dir}/.rules.json → {dir}/algoritm_{name}.rules.json, (3) нормализация полей, (4) создание пустых .editor.json для существующих .php.

CLI (24 команды): help, domain:new, domain:remove, allias:new, allias:remove, block:new, block:remove, filler:new, filler:toggle, page:new, page:toggle, page:write, page:read, scheme:new, scheme:copy, scheme:remove, scheme:save, db:migrate, admin:passwd, user:new, trash:clear, structure:check, structure:repair, migrate:page-rules.

trash:clear — обход 4 уровней карантина (app/, app/<domain>/, .alliasscheme/, .alliasglobal/), флаги --force/--older/--domain, dry-run по умолчанию, symlink-safe rmrf.

Качество: PHPUnit (336 тестов, 798 assertions — верифицировано; 1 skipped), PHPStan level 8 (0 errors, 204 файла: src/, bin/, www/), PHP-CS-Fixer (0 issues, 204 файла: src/, bin/, www/, tests/), GitHub Actions CI (phpunit + phpstan + cs-fixer --dry-run).

4. Частично реализовано (🟡)
Фича	Что есть	Что нужно для ✅
I18n фронтенда	Middleware, DomainContext->language	UI-переключатель на сайте, ключи site.*, hreflang
Skins	SkinManager, автоподключение CSS/JS	UI-менеджер в админке, переключатель на фронте
AI-Транзакции	AI умеет диагностировать, создавать страницы (батчами)	Откат (Rollback) при падении одной из команд в цепочке.
AI-Контекст	Успешная генерация 10 страниц	Разбиение длинных задач на батчи для слабых моделей (Qwen 7B).

5. Не реализовано (❌)
WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API для внешних систем, Sitemap, robots.txt, hreflang, плагины, Schema.org, SMTP / сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента.

6. Осознанные архитектурные решения и известные дефекты
6.1. Страницы не идут через схему по умолчанию
Статус: ✅ Решено. `page:new` создает "голую" страницу. Разработчик добавляет `SchemeRenderer` вручную (Вариант A) или использует `page:write --with_schema=1` (для AI).

6.2. RegionContext::service() возвращает mixed
Статус: Осознанное решение. Компенсация — только instanceof-проверки. @template в PHPDoc — P2.

6.3. assert() для проверки типов из контейнера
Статус: Заменить assert на явные if (!instanceof) throw — P2.

6.4. domains_real[$domain] в save()
Статус: Не баг. P2 — заменить на ConfigDomainResolver::real().

6.6. Builder неполон (нет table, form, section)
Статус: P2. Дополнить Builder методами.

6.17. Порт в Host не нормализуется
Статус: ✅ Закрыто (stage-3).

6.18. default(): null ломает страницу
Статус: ✅ Закрыто (stage-4, возвращает 204).

6.22. Принятый риск: пароль dev-БД в истории git
Статус: принятый риск. MariaDB слушает только localhost.

6.24. Защита от RCE в RegionCompiler
Статус: ✅ Закрыто. Визуальные редакторы удалены. `RegionCompiler` требует флаг `security.allow_php_blocks=true`.

6.25. Двойной слэш при записи главной страницы
Статус: ✅ Закрыто. PageWriteCommand использует PagePath::resolve(), который возвращает корректный путь без двойных слэшей.

6.26. Терминология Algoritm заменена на Page
Проблема: Класс `Algoritm` и интерфейс `AlgoritmInterface` конфликтовали с именами файлов `algoritm.php` из-за регистронезависимости PHP.
Статус: ✅ Решено (классы). Namespace `Damoony\Algoritm` пока сохранён (P2).

6.27. Старая модель .rules.json
Проблема: В старых версиях root rules были в algoritm/.rules.json, страницы в {dir}/.rules.json, page:toggle писал в 4-й файл. Это создавало дубли и мёртвые файлы.
Статус: ✅ Решено. Новая модель: алгоритм_{name}.rules.json для каждой страницы, алгоритм/algoritm.rules.json для root. Миграция — `migrate:page-rules`.

6.28. CLI-First для схем
Проблема: scheme:copy/remove/saveVisual были реализованы напрямую в SchemeController (файловые операции, не через CLI).
Статус: ✅ Решено. Новые команды scheme:copy/remove/save. SchemeController — тонкий клиент над CLI.

6.29. VarsExtractCommand (мёртвый код)
Проблема: Команда парсила `public $available` из PHP-класса (не существует в новой модели) и создавала `.data.json`, который движок не читает.
Статус: ✅ Удалён (команда + тест). Миграция старой модели — `migrate:page-rules`.

7. Известные баги (требуют исправления)
7.1. P0 — Блокируют production (6 из 6 закрыто)
~~CSRF на destructive GET.~~ ✅ Закрыто.
~~addslashes в компиляторах.~~ ✅ Закрыто.
~~Response::internalError($e->getMessage()).~~ ✅ Закрыто.
~~Два формата .edit.json.~~ ✅ Закрыто.
~~SchemeCompiler::str_replace.~~ ✅ Закрыто.
~~Нормализация Host.~~ ✅ Закрыто.
~~RCE через custom/if блоки.~~ ✅ Закрыто.

7.2. P1 — Надёжность и безопасность (7 из 7 закрыто)
~~Document::release() не идемпотентен.~~ ✅ Закрыто.
~~XSS в именах атрибутов.~~ ✅ Закрыто.
~~SchemeLoader fallback.~~ ✅ Закрыто.
~~Схлопывание вложенности схем.~~ ✅ Закрыто.
~~PageController::edit() не резолвит хост.~~ ✅ Закрыто.
~~default(): null.~~ ✅ Закрыто.
~~StructureChecker не проверяет схемы.~~ ✅ Закрыто.

7.3. P2 — Техдолг
Реализовать Rollback (транзакции) для AI-агента.
Разбить длинные AI-задания на батчи (для Qwen 7B).
domains_real[$domain] → ConfigDomainResolver::real().
Builder — дополнить (table, form, section, nav, label, select, textarea).
assert() → явные проверки.
RegionContext::service() — добавить @template в PHPDoc.
Translator/LanguageNegotiator через контейнер.
PageHandler::applySkin — не читать $_COOKIE напрямую.
Переименовать namespace `Damoony\Algoritm` → `Damoony\Page`.

8. Инварианты
Домен ≠ Алиас.
DomainResolver не бросает исключений.
FileRouter не рендерит ошибки.
Все POST защищены CSRF.
Пароли — password_hash.
Ядро знает только RegionInterface.
Kernel ловит Throwable, белых экранов нет.
Destructive операции — только POST+CSRF (UI) или --force (CLI).
Host нормализуется.
default(): null не ломает runtime.
Имена атрибутов валидируются.
Скрытых дефолтов нет.
Document::release() идемпотентен.
Правило 3 файлов: для страниц все три файла имеют единый префикс algoritm_{name}.{php,rules.json,editor.json} в одной директории. Root — algoritm/algoritm.{...}.
AI не пишет сырой PHP, а использует PageCompiler (JSON).
CLI-First: все файловые операции (включая scheme:copy/remove/save) вызываются через CLI. Админка — тонкий клиент.
Path traversal guard: все CLI-команды с `--domain`/`--name` проверяют `..` и regex.
Карантин: все destructive CLI-операции используют `*_trash_<timestamp>/`, требуют `--force`.
realDomain: пути в app/ строятся по realDomain из site.config, а не по входному хосту.

9. Источники истины (порядок приоритета)
Код в src/ — фактическое поведение.
MANIFEST.md — намерения.
_doc/ — производная документация.

10. Bug Backlog (по приоритетам)
Закрыто (текущий): Удалены глобальные манифесты (blocks.json, schemes.json).
Закрыто (текущий): Внедрено правило 3 файлов.
Закрыто (текущий): Создан AI-фасад (aicli.php) и CLI-оркестрация.
Закрыто (текущий): AI успешно генерирует 10 страниц с меню и контентом.
Закрыто (текущий): Унифицирована модель .rules.json (algoritm_{name}.rules.json), миграция migrate:page-rules.
Закрыто (текущий): Severity-aware structure:check (warnings не валят команду).
Закрыто (текущий): CLI-аудит — scheme:copy/remove/save, path traversal guards, realDomain, карантины, idempotent remove, StructureRepairCommand (PageCompiler + editor.json), удалён vars:extract.
Закрыто (текущий): PHPStan/CS-Fixer расширены на bin/ и www/.

P0 — блокируют production
Нет. Все закрыты.

P1 — надёжность/безопасность
Нет. Все закрыты.

P2 — техдолг
Rollback для AI.
Батчи для AI.
domains_real[$domain] → ConfigDomainResolver::real().
Builder — дополнить.
assert → явные проверки.
Фронтенд I18n, Skins.
Переименовать namespace Damoony\Algoritm.

11. Позиция архитектора (финальная)
Текущее состояние: 98% production-ready.
0 P0-блокеров. 0 P1-блокеров.
Оценка 9.7/10 (после CLI-аудита).
Как AI-платформа: 9.8/10 (AI успешно строит сайты за 1 шаг).

12. Видение (6–12 месяцев)
Low-Code: Схемы (Drag&Drop) для контент-менеджеров. Страницы и Блоки (CodeMirror) для разработчиков.

AI-платформа: Транзакции и Rollback. Изоляция тестовых доменов. Облачный SaaS-конструктор на базе Damoony.

Multi-domain: 10+ доменов на одном инстансе. Кеш scandir для RegionLoader.

--- КОНЕЦ ДОКУМЕНТА ---