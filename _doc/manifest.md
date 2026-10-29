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
Тип блока script / visual / mixed	—
Правило	.rules.json рядом со страницей	Конфиг
Запрещено: "module", "plugin", "widget", "template".

3. Реализовано (✅)
Ядро: DI (PSR-11), Logger (PSR-3), Env, ErrorHandler, Kernel, Middleware (RateLimit, SecurityHeaders, Language, Skin).

Домен/Алиас: domain:new, allias:new, allias:remove, domains_*, alliases_*, stubs/*.html.

Роутинг: .rules.json, флаги available/auth/search/default, regex-параметры, 404 для неизвестных путей.

Схемы/Регионы: SchemeLoader, SchemeRenderer, RegionLoader (сканирует .alliasglobal/*/), RegionRegistry (disable/useGlobal), Algoritm::setContent, филлеры <name>_<allias>.php, иерархия local → global → 500.

UI: Node, Element, Attrs, Text, Fragment, Group, Builder (12 методов), Document, Meta, Form, Field, Table, Button, Message. XSS-экранирование на уровне Attrs/Text.

Auth: DbAuth (password_hash), SessionAuth, Csrf, login/logout, логирование в var/admin_logs.json, brute-force (5 → бан 15 мин).

БД: PDO, MigrationRunner, MariaDB/MySQL.

Админка: Вход/выход, профиль, I18n (RU/EN/ZH), Обзор системы, Домены, Алиасы, Блоки, Схемы, Филлеры, Страницы, Схемы алиаса, БД алиаса, Заглушки, Безопасность, Настройки, CodeMirror (локально), Safe Mode. Визуальные редакторы для Блоков, Схем (Drag&Drop, editor.json), Страниц.

Low-Code: PageCompiler, RegionCompiler, .edit.json рядом с .php, статусы слотов disabled/global/local.

Диагностика: StructureChecker (наличие файлов, стыковка филлер↔слот), CLI structure:check, страница «Обзор».

CLI (17 команд): help, domain:new, allias:new, allias:remove, block:new, block:remove, filler:new, filler:toggle, page:new, page:toggle, scheme:new, db:migrate, vars:extract, admin:passwd, user:new, trash:clear, structure:check.

Качество: PHPUnit (48 тестовых файлов, 245 тестов, 542 assertions — верифицировано), PHPStan level 8 (0 errors), PHP-CS-Fixer (0 issues), GitHub Actions CI.

4. Частично реализовано (🟡)
Фича	Что есть	Что нужно для ✅
I18n фронтенда	Middleware, DomainContext->language	UI-переключатель на сайте, ключи site.*, hreflang
Skins	SkinManager, автоподключение CSS/JS	UI-менеджер в админке, переключатель на фронте
.edit.json	Два формата ({blocks} старый, {slots} новый); edit()/save() блокируют legacy (stage-2a)	Автоматическая миграция старого → новый (сейчас — ручная)
StructureChecker	Наличие файлов, стыковка филлер↔слот	Проверка .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки
blocks.json	Записывается CLI	Не читается RegionLoader (источник — папки)
5. Не реализовано (❌)
WYSIWYG (свободная вёрстка), Headless/REST/GraphQL API, Sitemap, robots.txt, hreflang, плагины, Schema.org, SMTP / сброс пароля, Redis/Memcached/CDN, WebSocket, мультиязычность контента (перевод текста страниц).

6. Осознанные архитектурные решения и известные дефекты
6.1. Ручные страницы не идут через схему по умолчанию (Известный дефект API)
Проблема: page:new создаёт algoritm_about extends Algoritm, не вызывающий SchemeRenderer. Визуальное сохранение того же файла через PageCompiler схему включает. Пользователь о разнице не знает — это скрытая магия, противоречащая принципу Explicit over implicit.

Дополнительно: AlgoritmInterface::default() разрешает Node|Response|null. PageHandler::toResponse() на null бросает RuntimeException → 500. Интерфейс разрешает то, что runtime ломает. Это противоречие контракта (см. §6.18).

Статус: P1. Требуется либо фикс API (по умолчанию — схема, Response — явное исключение), либо обновление guide.md §5 с предупреждением.

6.2. RegionContext::service() возвращает mixed
Почему: Service Locator — паттерн с известными минусами, но альтернатива (10+ сервисов в конструкторе Algoritm) неудобна для контент-разработчика. В PHP 8.4 нет generics.

Компенсация (фактическая): В коде нет @template в PHPDoc. Компенсация — только instanceof-проверки на стороне вызывающего. PHPStan видит возвращаемый mixed и не поможет внутри service().

Статус: Осознанное решение, не баг. Добавление @template в PHPDoc — P2.

6.3. assert() для проверки типов из контейнера
Почему: assert() — контракт между разработчиками, в prod zend.assertions = -1.

Ревизия: PHPStan верит assert($x instanceof Y) и сужает тип. Но assert() не выполняется в runtime на prod. Если контейнер вернёт null — TypeError → 500. Аргумент «CI поймает» неверен — CI статический.

Статус: Заменить assert на явные if (!instanceof) throw — P2.

6.4. domains_real[$domain] в save()
Почему работает: domains_real — карта «входящий → реальный». Для любого $domain (входящего или реального) domains_real[$domain] вернёт реальный при условии self-mapping ("example.ru": "example.ru" в install.md).

Ограничение: Если в конфиге нет self-mapping для свежесозданного домена — Undefined array key → 500 в PageController::save() и BlockController::save().

Статус: Не баг в штатной конфигурации. P2 — заменить на ConfigDomainResolver::real() для устойчивости к конфигу без self-mapping.

6.5. SchemeLoader возвращает fallback, если схемы нет
Почему: Для dev-режима «страница без схемы» не должна падать с 500.

Проблема: В коде не реализовано ограничение. SchemeLoader::load() всегда возвращает fallback div.layout > main[slot=center], нарушая инвариант «Скрытых дефолтов нет». Переформулировка инварианта («в prod нет, в dev — с логом») не реализована.

Статус: P1. Требуется if (!$env->isDev()) throw new RuntimeException(...) + логирование в dev.

6.6. Builder неполон (нет table, form, section)
Почему: Builder — хелперы для частых операций. Для сложных структур используется new Element('table') или компоненты (Table, Form). Явно разрешено (см. §8.1 п.6).

Статус: P2. Дополнить Builder методами table, form, section, nav, label, select, textarea.

6.7. DomainContext->scheme используется непоследовательно
Проблема: PageController::edit() читает имя схемы из Config напрямую, а не из DomainContext->scheme. Работает, но дублирование.

Статус: Не баг. P2.

6.15. SchemeController::edit() схлопывает вложенные схемы
Проблема: При сохранении схемы через визуальный редактор вложенная структура (например div > main-wrap > [left, center, right]) схлопывается в плоский div.layout из корневых слотов. Вложенность теряется без предупреждения.

Статус: P1. Требуется либо сохранение вложенности, либо явное предупреждение «схема содержит вложенность, редактор её схлопнёт».

6.16. PageController::edit() не резолвит входящий хост
Проблема: PageController::edit() читает domains_scheme[$domain]. Если $domain — входящий хост (например damoony3.ru), имя схемы не найдётся — слоты в редакторе будут пустыми или дефолтными. Тихая деградация UX.

Статус: P1. Требуется ConfigDomainResolver::real($domain).

6.17. Порт в Host не нормализуется (Новое)
Проблема: ConfigDomainResolver::resolve() делает только strtolower($host). FrontController берёт Host из заголовка — с портом. Значит 192.168.1.10:8080 не сматчится с 192.168.1.10 в конфиге. При развёртывании по IP с нестандартным портом — 404 на всё.

Дополнительно: трейлинг-точка (example.ru.) тоже не отрезается — FQDN-валидный хост не матчится.

Статус: P0 для сценария IP-деплоя с портом; P1 для общего случая. Требуется нормализация Host (отрезать :port, trailing dot, lowercase) в ConfigDomainResolver.

6.18. Algoritm::default(): null ломает страницу (Новое)
Проблема: AlgoritmInterface::default() разрешает Node|Response|null. PageHandler::toResponse() на null бросает RuntimeException('Page returned null; default() must return Node or Response') → Kernel ловит → 500. Контракт интерфейса противоречит поведению runtime.

Статус: P1. Либо убрать null из интерфейса, либо трактовать null как Response::noContent() (204).

6.19. FrontController собирает Translator/LanguageNegotiator вручную (Новое)
Проблема: В FrontController::handle() — new LanguageNegotiator() и new Translator($lang, 'ru') напрямую, минуя контейнер. Нарушает «Explicit over implicit» и «Один способ собрать». Сервис i18n в контейнере мог бы быть.

Статус: P2. Через контейнер + RegionContext->service('i18n').

6.20. PageHandler::applySkin читает $_COOKIE['skin'] напрямую (Новое)
Проблема: SkinCookieMiddleware документирован, но PageHandler::applySkin() читает $_COOKIE['skin'] напрямую, минуя middleware. Рассинхронизация между документацией middleware и фактическим кодом. Валидация allowed в PageHandler — своя, дублирует middleware.

Статус: P2. Либо читать из DomainContext->skin (если middleware его заполняет), либо задокументировать, что SkinCookieMiddleware только пишет cookie, а читает PageHandler.

6.21. DomainContext->logic — мёртвое поле (Новое)
Проблема: DomainContext содержит logic: string (дефолт 'alpha.0.0'), которое вычисляется в ConfigDomainResolver::resolve(), но нигде в коде не читается. Ни PageHandler, ни FrontController, ни SchemeLoader.

Статус: P2. Либо задокументировать назначение и использовать, либо удалить.

7. Известные баги (требуют исправления)
7.1. P0 — Блокируют production (4 из 6 закрыто: stage-1 — компиляторы, stage-2a — legacy .edit.json, stage-2b — CSRF на destructive GET)
~~CSRF на destructive GET.~~ ✅ Закрыто (stage-2b): requirePost + requireCsrf в SchemeController::copy/delete, BlockController::remove, StubController::remove. UI переведён на POST-формы. StubController::remove — добавлена валидация имени (path traversal).

~~addslashes в компиляторах.~~ ✅ Закрыто (stage-1): PageCompiler/RegionCompiler используют var_export($content, true). Регрессия закрыта: _dev/tests/Unit/Page/PageCompilerTest.php, _dev/tests/Unit/Region/RegionCompilerTest.php.

Response::internalError($e->getMessage()) в PageHandler. Утечка путей и классов в prod. (Не ErrorPage:: — Response::.) — **открыт, stage-3.**

~~Два формата .edit.json без миграции.~~ ✅ Закрыто (stage-2a): PageController::edit() и save() блокируют legacy {blocks} с понятным сообщением; about/.edit.json удалён. Автоматическая миграция {blocks} → {slots} не реализована — legacy-файлы нужно удалить вручную.

~~SchemeCompiler::str_replace('array (', '[').~~ ✅ Закрыто (stage-1): str_replace удалён, var_export как есть. Round-trip тест добавлен: _dev/tests/Unit/Scheme/SchemeCompilerTest.php (включая строки с (, ), array (, [x]).

Нормализация Host (§6.17). 192.168.1.10:8080 не матчится. Критично для IP-деплоя. — **открыт, stage-3.**

7.2. P1 — Надёжность и безопасность
Document::release() не идемпотентен. Повторный вызов дублирует <title>/<link>/<script>.

XSS в именах атрибутов Attrs::release(). Имена атрибутов не валидируются.

StructureChecker не ловит критичные проблемы: .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки, schemes.json ↔ файлы (ловит «сироту» visuo).

SchemeLoader fallback не проверяет dev (§6.5).

SchemeController::edit() схлопывает вложенные схемы (§6.15).

PageController::edit() не резолвит входящий хост (§6.16).

Algoritm::default(): null ломает страницу (§6.18).

7.3. P2 — Техдолг
domains_real[$domain] → ConfigDomainResolver::real() (§6.4).

PageController::edit() → DomainContext->scheme (§6.7).

Builder — дополнить (§6.6).

assert() → явные проверки (§6.3).

RegionContext::service() — добавить @template в PHPDoc (§6.2).

Убрать мёртвый код: RouteMatch::forbidden(), Group (дублирует Element), DomainContext->logic (§6.21).

Удалить .data.php блоков и blocks.json или начать их читать (§4).

Translator/LanguageNegotiator через контейнер (§6.19).

PageHandler::applySkin — не читать $_COOKIE напрямую (§6.20).

Тесты: StructureCheckerTest, PageHandlerTest (схема по умолчанию). PageCompilerTest, RegionCompilerTest, SchemeCompilerTest — ✅ закрыты в stage-1.

Фронтенд I18n, Skins (§4).

8. Инварианты
8.1. Соблюдаются
Домен ≠ Алиас.

DomainResolver не бросает исключений.

FileRouter не рендерит ошибки.

Все POST защищены CSRF.

Пароли — password_hash.

Новый UI собирается через Builder там, где это возможно.

Ядро знает только RegionInterface.

Kernel ловит Throwable, белых экранов нет.

Destructive операции — только POST+CSRF (stage-2b).

8.2. Нарушаются (баги из §7)
~~Destructive операции — только POST (§7.1 п.1).~~ ✅ Соблюдается с stage-2b.

~~Один формат .edit.json (§7.1 п.4).~~ ✅ Legacy блокируется с stage-2a (автомиграции нет, но регрессии нет).

Document::release() идемпотентен (§7.2 п.1).

Имена атрибутов валидируются (§7.2 п.2).

Скрытых дефолтов нет (§6.5).

Вложенность схем сохраняется (§6.15).

Host нормализуется (§6.17).

default(): null не ломает runtime (§6.18).

9. Источники истины (порядок приоритета)
Код в src/ — фактическое поведение.

MANIFEST.md — намерения.

_doc/ — производная документация.

Комментарии в коде.
При расхождении п.1 и п.2 — это баг (см. §7 и §10).

10. Bug Backlog (по приоритетам)
Закрыто в stage-1: addslashes → var_export (PageCompiler, RegionCompiler); SchemeCompiler::str_replace удалён. Покрыто тестами.

Закрыто в stage-2a: legacy {blocks} .edit.json блокируется в PageController::edit()/save(); about/.edit.json удалён.

Закрыто в stage-2b: CSRF на destructive GET (SchemeController::copy/delete, BlockController::remove, StubController::remove); UI переведён на POST-формы; StubController::remove валидирует имя (path traversal).

P0 — блокируют production
~~CSRF на GET-delete/copy.~~ ✅ Закрыто (stage-2b).

Response::internalError($e->getMessage()) → generic сообщение + лог.

~~Миграция .edit.json на новый формат.~~ ✅ Частично (stage-2a): edit()/save() блокируют legacy. Автомиграция — P1.

Нормализация Host (порт, trailing dot, lowercase) в ConfigDomainResolver.

P1 — надёжность/безопасность
Document::release() — идемпотентность.

Attrs::release() — валидация имён атрибутов.

StructureChecker — расширить проверки (schemes.json ↔ файлы, .edit.json ↔ PHP, legacy .edit.json).

SchemeLoader — проверка Env::isDev().

PageController::edit() — резолв входящего хоста.

Algoritm::default(): null — убрать из интерфейса или трактовать как 204.

P2 — техдолг
domains_real[$domain] → ConfigDomainResolver::real().

PageController::edit() → DomainContext->scheme.

Builder — дополнить.

assert → явные проверки.

RegionContext::service() — @template в PHPDoc.

Убрать мёртвый код (RouteMatch::forbidden, Group, DomainContext->logic).

Удалить .data.php блоков и blocks.json или начать читать.

Translator/LanguageNegotiator через контейнер.

PageHandler::applySkin — не читать $_COOKIE напрямую.

Тесты для StructureChecker и PageHandler.

11. Позиция архитектора (финальная)
Принято из аудита: баги из §7.1 и §7.2 — реальные. §6.1, §6.3, §6.5, §6.15, §6.16, §6.17, §6.18 — переклассифицированы в дефекты. §6.4 — работает при self-mapping, ограничение зафиксировано.

Отвергнуто как «баги»: §6.2, §6.6, §6.7, §6.19, §6.20, §6.21 — осознанные решения или улучшения (P2).

Текущее состояние: ~80% production-ready.

2 P0-блокера (утечка путей через getMessage, порт в Host). Закрыты: stage-1 — компиляторы, stage-2a — legacy .edit.json, stage-2b — CSRF.

~0.5 дня работы до деплоя.

Оценка 7.5/10.

После P0 (stage-3): 8.2/10, 85%.
После P0+P1: 8.8/10, 90%.
Как AI-платформа: 9/10.
Как архитектурный эксперимент: 9/10.

12. Видение (6–12 месяцев)
Low-Code: визуальный редактор безопасен для контент-менеджера — сохранение не теряет данные, вложенные схемы сохраняются, legacy .edit.json мигрирован автоматически. WYSIWYG — не цель; цель — надёжный конструктор блоков.

AI-платформа: REST API (read-only) для внешних агентов, structure:check покрывает .edit.json ↔ PHP и schemes.json ↔ файлы. Плагины — не цель; цель — предсказуемый CLI.

Multi-domain: 10 доменов на одном инстансе без деградации. Кеш scandir для RegionLoader. UI-менеджер скинов. hreflang для I18n.

--- КОНЕЦ ДОКУМЕНТА ---