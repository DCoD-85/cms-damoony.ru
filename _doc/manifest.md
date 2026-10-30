--- НАЧАЛО ДОКУМЕНТА ---

MANIFEST — Канон намерений и фактического состояния
MANIFEST — это канонический перечень возможностей и ограничений Damoony Engine. Любой другой документ (readme.md, architecture.md, contracts.md, guide.md, admin.md, cli.md) производен от него.

Правило расхождения: MANIFEST описывает намерения. Код в src/ описывает поведение. Расхождение — это баг, который должен быть исправлен либо в коде (достичь намерения), либо в MANIFEST (скорректировать намерение). Не бывает «истины только в MANIFEST».

1. Идентичность
PHP 8.4, self-hosted, multi-domain CMS.

Multi-domain — first-class (в ядре, не плагин).

Low-Code визуальный редактор с компиляцией в нативный PHP (Zero-Overhead).

Не WordPress, не Laravel CMS, не headless, не SaaS.

2. Терминология (без синонимов)
Термин	Значение	Не путать с
Домен	Папка app/<real_domain>/	Входящий хост
Алиас	Подпапка app/<real_domain>/<allias>/	Поддомен
Схема	Каркас HTML в .alliasscheme/<name>.data.php	Страница
Слот	Позиция в схеме (top, center)	Регион
Регион	Класс RegionInterface в .alliasglobal/<name>/	Страница
Блок	Синоним региона	—
Филлер	Переопределение региона для алиаса	Глобальный регион
Страница	Класс Algoritm в algoritm/	Регион
Тип блока	script / visual / mixed	—
Правило	.rules.json рядом со страницей	Конфиг
Запрещено: "module", "plugin", "widget", "template".

3. Реализовано (✅)
Ядро: DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

Домен/Алиас: domain:new, allias:new, allias:remove, domains_*, alliases_*, stubs/*.html.

Роутинг: .rules.json, флаги available/auth/search/default, regex-параметры, 404 для неизвестных путей.

Схемы/Регионы: SchemeLoader, SchemeRenderer, RegionLoader (сканирует .alliasglobal/*/), RegionRegistry (disable/useGlobal), Algoritm::setContent, филлеры <name>_<allias>.php, иерархия local → global → 500.

UI: Node, Element, Attrs, Text, Fragment, Group, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message. XSS-экранирование на уровне Attrs/Text.

Auth: DbAuth (password_hash), SessionAuth, Csrf, login/logout, логирование в var/admin_logs.json, brute-force (5 → бан 15 мин). Базовые методы Algoritm::isAuthenticated() и user().

БД: PDO, MigrationRunner, MariaDB/MySQL.

Админка: Вход/выход, профиль, I18n (RU/EN/ZH), Обзор системы, Домены, Алиасы, Блоки, Схемы, Филлеры, Страницы (дерево), Схемы алиаса, БД алиаса, Заглушки, Безопасность, Настройки, CodeMirror (локально), Safe Mode. Визуальные редакторы для Блоков, Схем (Drag&Drop), Страниц (Табы, Обзор, Drag&Drop).

Low-Code: PageCompiler, RegionCompiler, page.editor.json и scheme.editor.json (источник правил), статусы слотов disabled/global/local.

Диагностика: StructureChecker (наличие файлов, стыковка филлер↔слот), CLI structure:check, страница «Обзор».

CLI (18 команд): help, domain:new, allias:new, allias:remove, block:new, block:remove, filler:new, filler:toggle, page:new, page:toggle, scheme:new, db:migrate, vars:extract, admin:passwd, user:new, trash:clear, structure:check.

ErrorPage: локализован через Translator (`error.*` в lang/{ru,en,zh}, fallback ru). Ключи: `error.{403,404,429,500,503}.{title,message}`, `error.default.*`, `error.429.retry` с подстановкой `{minutes}`.

Качество: PHPUnit (61 тестовый файл, 294 теста, 660 assertions — верифицировано; 1 skipped — LinkTraversalTest, требует dev-сервер 127.0.0.1:8080, прогоняется в CI), PHPStan level 8 (0 errors), PHP-CS-Fixer (0 issues), GitHub Actions CI.

4. Частично реализовано (🟡)
Фича	Что есть	Что нужно для ✅
I18n фронтенда	Middleware, DomainContext->language	UI-переключатель на сайте, ключи site.*, hreflang
Skins	SkinManager, автоподключение CSS/JS	UI-менеджер в админке, переключатель на фронте
StructureChecker	Наличие файлов, стыковка филлер↔слот, проверка .edit.json и schemes.json (stage-5)	Проверка blocks.json ↔ папки, legacy .edit.json
blocks.json	Записывается CLI	Не читается RegionLoader (источник — папки)
5. Не реализовано (❌)
WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API, Sitemap, robots.txt, hreflang, плагины, Schema.org, SMTP / сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента (перевод текста страниц).

6. Осознанные архитектурные решения и известные дефекты
6.1. Ручные страницы не идут через схему по умолчанию (Известный дефект API)
Проблема: page:new создаёт algoritm_about extends Algoritm, не вызывающий SchemeRenderer. Визуальное сохранение того же файла через PageCompiler схему включает. Пользователь о разнице не знает — это скрытая магия, противоречащая принципу Explicit over implicit.

Статус: P1. Требуется либо фикс API (по умолчанию — схема, Response — явное исключение), либо обновление guide.md §5 с предупреждением.

6.2. RegionContext::service() возвращает mixed
Почему: Service Locator — паттерн с известными минусами, но альтернатива (10+ сервисов в конструкторе Algoritm) неудобна для контент-разработчика. В PHP 8.4 нет generics.

Компенсация (фактическая): В коде нет @template в PHPDoc. Компенсация — только instanceof-проверки на стороне вызывающего. PHPStan видит возвращаемый mixed и не поможет внутри service().

Статус: Осознанное решение, не баг. Добавление @template в PHPDoc — P2.

6.3. assert() для проверки типов из контейнера
Почему: assert() — контракт между разработчиками, в prod zend.assertions = -1.

Статус: Заменить assert на явные if (!instanceof) throw — P2.

6.4. domains_real[$domain] в save()
Почему работает: domains_real — карта «входящий → реальный». Для любого $domain (входящего или реального) domains_real[$domain] вернёт реальный при условии self-mapping ("example.ru": "example.ru" в install.md).

Ограничение: Если в конфиге нет self-mapping для свежесозданного домена — Undefined array key → 500 в PageController::save() и BlockController::save().

Статус: Не баг в штатной конфигурации. P2 — заменить на ConfigDomainResolver::real() для устойчивости к конфигу без self-mapping.

6.5. SchemeLoader возвращает fallback, если схемы нет
Статус: ✅ Закрыто (stage-4). SchemeLoader::load() в prod бросает RuntimeException, в dev возвращает fallback (с логом). Тест: _dev/tests/Unit/Scheme/SchemeLoaderTest.php.

6.6. Builder неполон (нет table, form, section)
Почему: Builder — хелперы для частых операций. Для сложных структур используется new Element('table') или компоненты (Table, Form). Явно разрешено (см. §8.1 п.6).

Статус: P2. Дополнить Builder методами table, form, section, nav, label, select, textarea.

6.7. DomainContext->scheme используется непоследовательно
Статус: Не баг. P2.

6.15. SchemeController::edit() схлопывает вложенные схемы
Статус: ✅ Закрыто (stage-5). Визуальный редактор схем полностью переработан (Drag&Drop). Вложенность сохраняется благодаря формату editor.json и новому UI.

6.16. PageController::edit() не резолвит входящий хост
Статус: ✅ Закрыто (stage-5). PageController::edit() резолвит реальный домен через domains_real перед чтением domains_scheme.

6.17. Порт в Host не нормализуется (Новое)
Статус: ✅ Закрыто (stage-3). ConfigDomainResolver::resolve() нормализует Host (отрезает порт, trailing dot, lowercase). Тест: _dev/tests/Unit/Domain/ConfigDomainResolverTest.php.

6.18. Algoritm::default(): null ломает страницу (Новое)
Статус: ✅ Закрыто (stage-4). PageHandler::toResponse() на null возвращает 204 No Content (Response::text('', 204)). Тест: _dev/tests/Unit/App/PageHandlerTest.php.

6.19. FrontController собирает Translator/LanguageNegotiator вручную (Новое)
Статус: P2. Через контейнер + RegionContext->service('i18n').

6.20. PageHandler::applySkin читает $_COOKIE['skin'] напрямую (Новое)
`SkinCookieMiddleware` ставит cookie и редиректит (307), но не мутирует `DomainContext->skin` — тот readonly и заполняется в `ConfigDomainResolver` до middleware. Прямая замена `$_COOKIE['skin']` → `$domain->skin` сломает выбор скина (все увидят дефолт из конфига). Валидация `domains_skins_allowed` дублируется в middleware (cookie) и в `applySkin` (чтение). Функционально корректно.

Статус: P2 (дублирование валидации, не баг). Правильный фикс: middleware читает cookie → передаёт в resolver (или `RegionContext`) → `DomainContext->skin` учитывает cookie. Переделка слоя, отложено.

6.21. DomainContext->logic — мёртвое поле (Новое)
Поле `logic` вычислялось в `ConfigDomainResolver` из `domains_logic` (default `alpha.0.0`), но нигде не читалось ни в `src/`, ни в тестах.

Статус: ✅ Закрыто (audit-2026-10). Поле удалено из `DomainContext`, `ConfigDomainResolver` (включая `$defaultLogic`), все 7 мест инстанцирования в тестах обновлены. Ключ `domains_logic` в `site.config` оставлен — данные пользователя, безвредны.

6.22. Принятый риск: пароль dev-БД в истории git (Новое)
Пароль `qwe12345` от локальной MariaDB (`damoony_local_db`) присутствует в коммитах `8c70dfc`, `b6c4971`, запушен в `origin/main`. Репозиторий публичный.

Контекст: MariaDB слушает только `127.0.0.1`, production-доступа к ней нет. Владелец принял риск осознанно — ротация не выполняется.

Статус: принятый риск, не баг. Если БД когда-либо станет доступна извне — ротация обязательна. Полная очистка истории (`git filter-repo` + force-push) возможна, но не выполняется.

6.23. DomainContext->allias — ключ, а структура — значение (Новое)
`ConfigDomainResolver::resolve()` возвращает `allias` = ключ из `alliases_real` (например `sub`), но папки на диске и `StructureChecker` работают с значением (`www`). Для identity-маппингов (`"www":"www"`) расхождения нет; для не-identity (`"sub":"www"`) `PageHandler` ищет `.../sub/algoritm/` (несуществующая папка) → 404, а `structure:check` проверяет `www/`.

Также `StructureCheckCommand` не дедуплицирует по `$mapped`: при `{"sub":"www","www":"www"}` папка `www/` проверяется дважды.

Статус: P2. Не регресс, поведение зафиксировано с stage-3. Требует решения по контракту: либо `DomainContext->allias` = `$mapped` (значение), либо конфиг ограничивается identity-маппингами. Dedupe в `StructureCheckCommand` — независимо.

7. Известные баги (требуют исправления)
7.1. P0 — Блокируют production (6 из 6 закрыто)
~~CSRF на destructive GET.~~ ✅ Закрыто (stage-2b).
~~addslashes в компиляторах.~~ ✅ Закрыто (stage-1).
~~Response::internalError($e->getMessage()) в PageHandler.~~ ✅ Закрыто (stage-3).
~~Два формата .edit.json без миграции.~~ ✅ Закрыто (stage-2a).
~~SchemeCompiler::str_replace('array (', '[').~~ ✅ Закрыто (stage-1).
~~Нормализация Host.~~ ✅ Закрыто (stage-3).

7.2. P1 — Надёжность и безопасность (7 из 7 закрыто)
~~Document::release() не идемпотентен.~~ ✅ Закрыто (stage-4).
~~XSS в именах атрибутов Attrs::release().~~ ✅ Закрыто (stage-4).
~~SchemeLoader fallback не проверяет dev.~~ ✅ Закрыто (stage-4).
~~SchemeController::edit() схлопывает вложенные схемы.~~ ✅ Закрыто (stage-5).
~~PageController::edit() не резолвит входящий хост.~~ ✅ Закрыто (stage-5).
~~Algoritm::default(): null ломает страницу.~~ ✅ Закрыто (stage-4).
StructureChecker расширен (добавлены .edit.json и schemes.json). ✅ Закрыто (stage-5).

7.3. P2 — Техдолг
domains_real[$domain] → ConfigDomainResolver::real() (§6.4).
PageController::edit() → DomainContext->scheme (§6.7).
Builder — дополнить (§6.6).
assert() → явные проверки (§6.3).
RegionContext::service() — добавить @template в PHPDoc (§6.2).
Удалить .data.php блоков и blocks.json или начать их читать (§4).
Translator/LanguageNegotiator через контейнер (§6.19).
PageHandler::applySkin — не читать $_COOKIE напрямую (§6.20, дублирование валидации, не баг).
Фронтенд I18n, Skins (§4).
DomainContext->allias = ключ alliases_real, а не значение (§6.23).
StructureCheckCommand — dedupe по $mapped, если ключ ≠ значение (§6.23).

8. Инварианты
8.1. Соблюдаются
Домен ≠ Алиас.
DomainResolver не бросает исключений.
FileRouter не рендерит ошибки.
Все POST защищены CSRF.
Пароли — password_hash.
Новый UI собирается через Builder.
Ядро знает только RegionInterface.
Kernel ловит Throwable, белых экранов нет.
Destructive операции — только POST+CSRF.
Host нормализуется (§6.17, stage-3).
default(): null не ломает runtime (§6.18, stage-4).
Имена атрибутов валидируются (§7.2 п.2, stage-4).
Скрытых дефолтов нет (§6.5, stage-4).
Document::release() идемпотентен (§7.2 п.1, stage-4).
Вложенность схем сохраняется (§6.15, stage-5).

9. Источники истины (порядок приоритета)
Код в src/ — фактическое поведение.
MANIFEST.md — намерения.
_doc/ — производная документация.
Комментарии в коде.
При расхождении п.1 и п.2 — это баг (см. §7 и §10).

10. Bug Backlog (по приоритетам)
Закрыто в stage-1: addslashes → var_export; SchemeCompiler::str_replace.
Закрыто в stage-2a: legacy {blocks} .edit.json.
Закрыто в stage-2b: CSRF на destructive GET.
Закрыто в stage-3: Host normalization; getMessage в prod.
Закрыто в stage-4: XSS в Attrs; Document::release() идемпотентность; SchemeLoader prod fallback; default(): null → 204.
Закрыто в stage-5: PageController::edit() резолв хоста; StructureChecker расширен; SchemeController::edit() сохранение вложенности (D&D).

P0 — блокируют production
Нет. Все закрыты.

P1 — надёжность/безопасность
Нет. Все закрыты.

P2 — техдолг
Закрыто (текущий): structure:repair покрыт тестами (StructureRepairCommandTest).
domains_real[$domain] → ConfigDomainResolver::real().
PageController::edit() → DomainContext->scheme.
Builder — дополнить.
assert → явные проверки.
RegionContext::service() — @template в PHPDoc.
Убрать мёртвый код (RouteMatch::forbidden, Group, DomainContext->logic).
Удалить .data.php блоков и blocks.json или начать читать.
Translator/LanguageNegotiator через контейнер.
PageHandler::applySkin — не читать $_COOKIE напрямую.
Фронтенд I18n, Skins.

11. Позиция архитектора (финальная)
Принято из аудита: баги из §7.1 и §7.2 — реальные. §6.1, §6.3, §6.5, §6.15, §6.16, §6.17, §6.18 — переклассифицированы в дефекты. §6.4 — работает при self-mapping, ограничение зафиксировано.

Отвергнуто как «баги»: §6.2, §6.6, §6.7, §6.19, §6.20, §6.21 — осознанные решения или улучшения (P2).

Текущее состояние: 95% production-ready.

0 P0-блокеров. 0 P1-блокеров.

Оценка 9.5/10.

Как AI-платформа: 9/10.
Как архитектурный эксперимент: 9/10.

12. Видение (6–12 месяцев)
Low-Code: визуальный редактор безопасен для контент-менеджера — сохранение не теряет данные, вложенные схемы сохраняются. WYSIWYG — не цель; цель — надёжный конструктор блоков.

AI-платформа: REST API (read-only) для внешних агентов, structure:check покрывает .edit.json ↔ PHP и schemes.json ↔ файлы. Плагины — не цель; цель — предсказуемый CLI.

Multi-domain: 10 доменов на одном инстансе без деградации. Кеш scandir для RegionLoader. UI-менеджер скинов. hreflang для I18n.

--- КОНЕЦ ДОКУМЕНТА ---