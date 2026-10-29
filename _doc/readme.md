--- НАЧАЛО ДОКУМЕНТА ---

Damoony Engine
Лёгкий PHP 8.4 движок для мультидоменных сайтов с собственной Low-Code UI-моделью.

Статус: 95% production-ready. 0 P0-блокеров. Полное состояние — MANIFEST.md. Если сомневаетесь в утверждении из любого документа — истина в MANIFEST.md §3–7, а не здесь.

Что это
Damoony Engine — переосмысление архитектуры SimplCMS. Сохраняет философию старого проекта (домены как единица организации, UI как дерево, регионы как плагины, файловый роутинг), но реализован с нуля на современных стандартах.

Главное отличие от классических CMS: Low-Code. Администратор собирает страницы, схемы и блоки мышкой, а движок компилирует данные в чистый PHP (Zero-Overhead).

Стек
PHP >= 8.4

Composer (PSR-4, PSR-3, PSR-11)

PHPUnit 10 (53 тестовых файла, 262 теста, 593 assertions — верифицировано; 1 skipped — env-dependent)

PHPStan (level 8)

MariaDB / MySQL (production) через PDO

SQLite (тесты)

Nginx + PHP-FPM (или Apache + mod_php)

Структура
text
bin/cli.php        — точка входа CLI
config/            — JSON-конфиги (site.config, engine.config, admin.config)
src/               — исходный код (PSR-4, Damoony)
lang/              — файлы переводов (ru.php, en.php, zh.php)
stubs/             — HTML-шаблоны для 503
www/               — публичная директория (index.php, admin/index.php, media/)
app/<domain>/      — пользовательские домены
migrations/        — SQL-миграции
var/               — runtime (логи, бан-листы, ratelimit)
_dev/              — тесты, конфиги PHPUnit/PHPStan
_doc/              — документация (этот файл и другие)
Ключевые особенности
Мультидоменность: управление многими сайтами из одной админки. Домен (инфраструктура) + Алиас (контент). 

Low-Code: Сборка схем и контента страниц мышкой (Drag&Drop). JSON-структуры (editor.json) компилируются в нативный PHP (PageCompiler, SchemeCompiler).

Строгая типизация блоков: script, visual, mixed. Жёсткие правила вложенности (visual — контейнер, script/mixed — листы).

UI как дерево: типизированное дерево Node/Element/Attrs с XSS-экранированием на уровне Attrs/Text.

Диагностика: StructureChecker, дашборд «Обзор».

Отказоустойчивость: Safe Mode в админке, HTML-заглушки, строгая типизация.

CLI и админка — равноправные интерфейсы (с оговорками, см. MANIFEST.md §3).

Принципы
Домен — единица организации. Всё скоупится по домену и алиасу.

UI — это дерево. Типизированное дерево узлов, не строки.

Регионы, Слоты, Filler. Шаблон собирается из блоков; схемы используют слоты.

Управление из кода. Страницы могут управлять регионами на лету.

Роутинг — файловые правила. .rules.json рядом со страницами.

Один способ сделать что-то. Один слой БД (PDO), один роутер, один автозагрузчик.

Explicit over implicit. Нет $GLOBALS, статических кэшей, магии __get.

Contract-first. Публичный API — интерфейсы.

CLI и Админка — равноправные интерфейсы (с оговорками).

Безопасность — основа. password_hash, CSRF, HttpOnly, брутфорс-защита.

Быстрый старт
Убедитесь, что в config/engine.config прописаны доступы к MariaDB, затем:

bash
composer install
php bin/cli.php db:migrate
php bin/cli.php admin:passwd --name=admin --password=secret
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
Откройте http://<домен>/ (сайт) и http://<домен>/admin/ (админка). Страницы с "auth": true в .rules.json требуют входа через /login.

⚠️ user:new создаёт пользователя сайта (для страниц с auth), не администратора. Админка использует отдельную авторизацию (admin.config).

Подробнее: install.md, cli.md, architecture.md, guide.md, admin.md.

Статус (по MANIFEST.md §3–4)
Реализовано (✅)
Ядро: DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

Домен/Алиас: domain:new, allias:new, allias:remove, domains_*, alliases_*, stubs/*.html.

Роутинг: .rules.json, флаги available/auth/search/default, regex-параметры, 404.

Схемы/Регионы: SchemeLoader, SchemeRenderer, RegionLoader, RegionRegistry, Algoritm::setContent, филлеры, иерархия local → global → 500.

UI: Node, Element, Attrs, Text, Fragment, Group, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message.

Auth: DbAuth, SessionAuth, Csrf, login/logout, логирование, брутфорс-защита. Базовые методы isAuthenticated() и user().

БД: PDO, MigrationRunner, MariaDB/MySQL.

Админка: полный CRUD доменов/алиасов/блоков/схем/страниц/филлеров/заглушек, I18n (RU/EN/ZH), System Health, CodeMirror, Safe Mode.

Low-Code: PageCompiler, SchemeCompiler, page.editor.json, scheme.editor.json, статусы слотов (Drag&Drop).

Диагностика: StructureChecker, structure:check, «Обзор».

CLI (18 команд, включая structure:check и structure:repair).

Качество: PHPUnit, PHPStan level 8, PHP-CS-Fixer, CI.

Частично (🟡)
I18n фронтенда: middleware и DomainContext->language есть, UI-переключателя и hreflang нет.

Skins: SkinManager и автоподключение CSS/JS есть, UI-менеджера и переключателя нет.

StructureChecker: не ловит blocks.json ↔ папки.

blocks.json: записывается CLI, не читается RegionLoader.

Не реализовано (❌)
WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API, sitemap, robots.txt, hreflang, плагины, Schema.org, SMTP/сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента, structure:repair.

Известные проблемы (по MANIFEST.md §7)
P0 — блокируют production
Нет. Все 6 P0 закрыты (stage-1, 2a, 2b, 3).

P1 — надёжность и безопасность
Нет. Все 7 P1 закрыты (stage-4, 5).

P2 — техдолг
domains_real[$domain] → ConfigDomainResolver::real(); PageController::edit() → DomainContext->scheme; Builder неполон; assert() в prod; @template в RegionContext::service(); мёртвый код (RouteMatch::forbidden, Group, DomainContext->logic); Translator мимо контейнера; applySkin мимо middleware.

Полный backlog и приоритеты — MANIFEST.md §10.

Производные документы
MANIFEST.md — канон. Источник истины о намерениях. При расхождении с кодом — баг.

install.md — развёртывание (требует проверки при запуске; расхождения возможны).

cli.md — CLI-команды.

admin.md — админка.

guide.md — руководство разработчика (ручные страницы, блоки, схемы, компиляторы).

architecture.md — жизненный цикл запроса и слои.

contracts.md — контракты (интерфейсы и ограничения).

philosophy.md — 10 принципов.

scenarios.md — ключевые сценарии.

i18n_skins_plan.md — статус I18n и скинов (частично устарел; истина — MANIFEST.md §4).

ai_system.md, ai_logic.md — инструкции для AI-агентов.

Важное замечание
Ранние версии этого файла (и других документов в _doc/) заявляли:

«235 тестов» — теперь верифицировано (262);
«скрытых дефолтов нет» — исправлено (SchemeLoader падает в prod);
«100% паритет CLI/UI» — неверно (§3);
«WYSIWYG» — не реализован (§5).

Эти утверждения исправлены в текущей версии. Если где-то в _doc/ осталась старая формулировка — доверяйте MANIFEST.md, а не ей.

--- КОНЕЦ ДОКУМЕНТА ---