--- НАЧАЛО ДОКУМЕНТА ---

Damoony Engine
Лёгкий PHP 8.4 движок для мультидоменных сайтов с архитектурой CLI-First и нативной интеграцией AI-агентов.

Статус: 98% production-ready. 0 P0-блокеров. Полное состояние — MANIFEST.md.

Что это
Damoony Engine — это AI-Native CMS. Она сочетает в себе скорость статического сайта (рендеринг за 0.8–3 мс) и мощь современных AI-агентов.

Главное отличие от классических CMS: 
1. **CLI-First**: CLI — это единственный источник истины. Админка и AI-агенты — это лишь безопасные обертки над CLI-командами.
2. **Безопасный Low-Code**: Визуальный Drag&Drop (конструктор) оставлен ТОЛЬКО для Схем (HTML-каркасов). Страницы и Блоки редактируются через CodeMirror. Это исключает RCE-уязвимости.
3. **AI-Интеграция**: Встроенный слой `bin/aicli.php` позволяет локальным LLM (например, Ollama) безопасно диагностировать систему и управлять ей.

Стек
PHP >= 8.4
Composer (PSR-4, PSR-3, PSR-11)
PHPUnit 10 (61 тестовый файл, 288 тестов, 644 assertions — верифицировано; 1 skipped — env-dependent)
PHPStan (level 8)
MariaDB / MySQL (production) через PDO
SQLite (тесты)
Nginx + PHP-FPM (или Apache + mod_php)

Структура
text
bin/cli.php        — точка входа CLI (Ядро)
bin/aicli.php      — безопасный фасад для AI-агентов
config/            — JSON-конфиги (site.config, engine.config, admin.config)
src/               — исходный код (PSR-4, Damoony)
lang/              — файлы переводов (ru.php, en.php, zh.php)
stubs/             — HTML-шаблоны для 503
www/               — публичная директория (index.php, admin/index.php, media/)
app/<domain>/      — пользовательские домены
migrations/        — SQL-миграции
var/               — runtime (логи, бан-листы, ratelimit)
_dev/              — тесты, конфиги PHPUnit/PHPStan
_doc/              — документация

Ключевые особенности
Мультидоменность: управление многими сайтами из одной админки. Домен (инфраструктура) + Алиас (контент).

Правило 3 файлов: Любая сущность (Страница, Блок, Схема) состоит из трех файлов: .php (код), .rules.json (настройки), .editor.json (данные UI). Никаких глобальных манифестов.

AI-Агенты: Агент (Gemma 4 26B / Ollama) может автономно проверять структуру, находить ошибки и лечить их через `aicli.php`.

Строгая типизация блоков: script, visual, mixed. Настройки хранятся в `.rules.json`.

UI как дерево: типизированное дерево Node/Element/Attrs с XSS-экранированием.

Диагностика: StructureChecker (проверка наличия всех 3 файлов), CLI structure:check и structure:repair.

Принципы
Домен — единица организации. Всё скоупится по домену и алиасу.
UI — это дерево. Типизированное дерево узлов, не строки.
CLI-First. Любая файловая операция вызывается через CLI. Админка и AI делегируют работу ему.
Explicit over implicit. Нет $GLOBALS, статических кэшей, магии __get.
Безопасность — основа. Удалены визуальные редакторы для блоков/страниц. RCE закрыт.

Быстрый старт
Убедитесь, что в config/engine.config прописаны доступы к MariaDB, затем:

bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=secret
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret

Откройте http://<домен>/ (сайт) и http://<домен>/admin/ (админка). Страницы с "auth": true в .rules.json требуют входа через /login.

⚠️ user:new создаёт пользователя сайта (для страниц с auth), не администратора. Админка использует отдельную авторизацию (admin.config).

Подробнее: install.md, cli.md, architecture.md, guide.md, admin.md, ai_agent.md.

Статус (по MANIFEST.md §3–4)
Реализовано (✅)
Ядро: DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

Безопасность (P0 закрыт): Удалены визуальные редакторы страниц/блоков. В RegionCompiler добавлен флаг `security.allow_php_blocks` (по умолчанию false).

Домен/Алиас: domain:new, allias:new, allias:remove, domains_*, alliases_*.

Роутинг: .rules.json, флаги available/auth/search/default, regex-параметры, 404.

Схемы/Регионы: SchemeLoader, SchemeRenderer, RegionLoader, RegionRegistry, филлеры.

Правило 3 файлов: Внедрено для Страниц, Блоков, Филлеров и Схем.

UI: Node, Element, Attrs, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message.

Auth: DbAuth, SessionAuth, Csrf, login/logout, логирование, брутфорс-защита.

БД: PDO, MigrationRunner, MariaDB/MySQL.

Админка: Тонкий клиент. CodeMirror для страниц/блоков. Визуальный Drag&Drop для схем. I18n (RU/EN/ZH), System Health, Safe Mode.

AI-Слой: `bin/aicli.php` — безопасный фасад (whitelist команд) для интеграции с Ollama.

CLI (22 команды, включая scheme:copy, scheme:remove, scheme:save, structure:check, structure:repair).

Качество: PHPUnit (288 тестов, 644 assertions), PHPStan level 8, PHP-CS-Fixer, CI.

Частично (🟡)
I18n фронтенда: middleware и DomainContext->language есть, UI-переключателя и hreflang нет.
Skins: SkinManager и автоподключение CSS/JS есть, UI-менеджера и переключателя нет.
AI-Транзакции: AI умеет диагностировать и лечить, но нет отката (Rollback) при падении одной из команд.

Не реализовано (❌)
WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API для внешних систем (кроме aicli), sitemap, robots.txt, hreflang, плагины, Schema.org, SMTP/сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента.

Известные проблемы (по MANIFEST.md §7)
P0 — блокируют production
Нет. Все 6 P0 закрыты.

P1 — надёжность и безопасность
Нет. Все 7 P1 закрыты.

P2 — техдолг
domains_real[$domain] → ConfigDomainResolver::real(); Builder неполон; assert() в prod; @template в RegionContext::service(); мёртвый код (RouteMatch::forbidden, Group); Translator мимо контейнера.

Производные документы
MANIFEST.md — канон. Источник истины о намерениях.

install.md — развёртывание.

cli.md — CLI-команды.

admin.md — админка.

guide.md — руководство разработчика (Вариант A/B, блоки, схемы).

architecture.md — жизненный цикл запроса (включая AI-слой).

contracts.md — контракты (интерфейсы и ограничения).

philosophy.md — 10 принципов.

scenarios.md — ключевые сценарии.

ai_agent.md — спецификация интеграции AI-агентов через aicli.php.

ai_agent.md — инструкции для AI-агентов.

--- КОНЕЦ ДОКУМЕНТА ---