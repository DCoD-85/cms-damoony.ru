--- НАЧАЛО ФАЙЛА ---

# Сравнительная карта: старый аудит -> новый движок

Документ показывает, что было в старом проекте (`_devdoc/_auditfinal/`, 36 файлов), во что это превратилось в новом движке (`src/`, ветка main) и каков статус переноса.

Статусы:
- transfer — перенесено как есть (интерфейс, принцип, команда).
- rebuild — концепция сохранена, реализация переписана с нуля.
- security — перенесено усиленным (закрыты уязвимости старого).
- drop — не перенесено, функционал убран или заменён.
- merge — функционал слился в другой модуль.

## Карта по исходным файлам

| NN | Было | Стало (пути в src/ и www/) | Статус |
|---|---|---|---|
| 01 | engine_core | src/Core/{Application,Container,Env,Config,Logger,ErrorHandler}.php | rebuild |
| 02 | engine_services | src/Http/{Request,Response,Kernel,MiddlewareStack,MiddlewareInterface}.php, src/Http/Middleware/*, src/Session/*, src/Security/Csrf.php, src/Support/Markdown.php | rebuild |
| 03 | engine_db | src/Db/{Connection,MigrationRunner}.php | rebuild |
| 04 | engine_library | src/Files/{Fs,Paths}.php, src/Support/Markdown.php | rebuild |
| 05 | engine_main_domain | src/App/{AppFactory,FrontController,PageHandler,PageHandlerInterface}.php, src/Domain/{ConfigDomainResolver,DomainContext,DomainResolverInterface}.php | rebuild |
| 06 | engine_language_algoritm | src/I18n/LanguageNegotiator.php, src/Http/Middleware/{LanguagePath,LanguageCookie}Middleware.php, src/Algoritm/* | rebuild |
| 07 | engine_security_middleware | src/Http/Middleware/{SecurityHeaders,RateLimit,JsonContentType,SkinCookie}Middleware.php | security |
| 08 | engine_tree | src/Routing/{FileRouter,RouteMatch,RouterInterface,RulesMatcher}.php | rebuild |
| 09 | corescripts_cli_lib_render | src/Cli/{Application,ArgvParser,CommandInterface,CommandRegistry,Context,HelpCommand,Renderer,Result}.php | transfer |
| 10 | corescripts_lib_data_admin | src/Core/Config.php, src/Admin/{Auth,AuthInterface}.php | merge |
| 11 | corescripts_commands_domain_allias | src/Cli/Commands/{DomainNew,AlliasNew,AlliasRemove}Command.php, src/Admin/Controller/{Domain,Allias,AliasDb,AliasScheme}Controller.php | rebuild |
| 12 | corescripts_commands_block_filler | src/Cli/Commands/{BlockNew,BlockRemove,FillerNew,FillerToggle}Command.php, src/Admin/Controller/{Block,Filler}Controller.php | rebuild |
| 13 | corescripts_commands_media_page | src/Cli/Commands/{PageNew,PageToggle}Command.php, src/Admin/Controller/{Page,Media,Editor}Controller.php | rebuild |
| 14 | corescripts_commands_settings_vars | src/Cli/Commands/{UserNew,VarsExtract}Command.php, src/Admin/Controller/SettingsController.php | rebuild |
| 15 | www_entry_admin_bootstrap | www/index.php, www/admin/index.php, src/Admin/{AdminRouter,View}.php | rebuild |
| 16 | www_tests_entry_lib | устаревший test-runner — заменён на PHPUnit в `_dev/tests/` | drop |
| 17 | www_tests_cases | старые suites — заменены на `_dev/tests/Scenarios/*` | drop |
| 18 | admin_core_router_auth_audit_scan_view | src/Admin/{AdminRouter,View,Auth,AuthInterface}.php, src/Admin/Controller/BaseController.php, src/Security/Csrf.php | security |
| 19 | admin_controllers_domain_allias_base | src/Admin/Controller/{BaseController,Domain,Allias,Login,Logout,Dashboard}Controller.php | rebuild |
| 20 | admin_controllers_block_filler | src/Admin/Controller/{Block,Filler}Controller.php | rebuild |
| 21 | admin_controllers_media_page | src/Admin/Controller/{Media,Page,Editor}Controller.php | security |
| 22 | admin_controllers_resource_scheme | src/Admin/Controller/SchemeController.php; ResourceController убран (ресурсы данных не переносятся) | rebuild + drop |
| 23 | admin_controllers_security_settings_stub | src/Admin/Controller/{Security,Settings,Stub}Controller.php | security |
| 24 | admin_views | перенесено в контроллеры через Builder/Component; отдельного слоя views нет | merge |
| 25 | visuo_entry_core | src/Ui/{Node,Element,Attrs,Document,Meta,Text,Fragment,Group,Builder,Slot,Component}.php | rebuild |
| 26 | visuo_attrs_struct | src/Ui/{Attrs,Slot}.php, src/Response/{AjaxStruct,MinStruct}.php | rebuild |
| 27 | visuoel_base_block_button_message_element | src/Ui/{Component,Button,Message,Group,Fragment}.php | rebuild |
| 28 | visuoel_field_input | src/Ui/{Field,Form}.php | rebuild |
| 29 | visuoel_form_table_tabs | src/Ui/{Form,Table}.php; Tabs убран — заменён слотами схемы | rebuild + drop |
| 30 | domain_allias_algoritm | src/Domain/*, src/App/PageHandler.php, src/Algoritm/{Algoritm,AlgoritmInterface}.php | rebuild |
| 31 | domain_algoritm_lang_migrations | migrations/*.sql, src/Cli/Commands/DbMigrateCommand.php, src/Db/MigrationRunner.php | rebuild |
| 32 | domain_dataprovider | src/Db/Connection.php (прямые PDO-запросы в Admin); фасад dataprovider убран | rebuild |
| 33 | domain_visuo_core | src/Ui/*, src/Support/Markdown.php, src/Auth/{AuthInterface,DbAuth,SessionAuth}.php | security |
| 34 | domain_alliasglobal | src/Region/{RegionInterface,RegionContext,RegionRegistry,RegionLoader,ContentRegion}.php | rebuild |
| 35 | domain_alliasscheme | src/Scheme/{SchemeLoader,SchemeRenderer}.php | rebuild |
| 36 | secure_dev_infrastructure | src/Admin/Auth.php (brute-force), src/Security/Csrf.php, src/Session/*, www/.htaccess | security |

## Итоги по статусам

- transfer: 09 — 1 файл
- merge: 10, 24 — 2 файла
- rebuild: 01, 02, 03, 04, 05, 06, 08, 11, 12, 13, 14, 15, 19, 20, 22 (частично), 25, 26, 27, 28, 29 (частично), 30, 31, 32, 34, 35 — 25 файлов
- security: 07, 18, 21, 23, 33, 36 — 6 файлов
- drop: 16, 17, 22 (ResourceController), 29 (Tabs) — 4 позиции (полностью или частично)

Итого: 36 из 36 покрыты (у 22 и 29 статус комбинированный: rebuild + drop).

## Что исчезло полностью

- `www/tests/` (старый test-runner) — заменён на PHPUnit в `_dev/tests/`.
- `engine/` (старый движок) — заменён на `src/` по PSR-4.
- `resource` (ресурсы данных) — не перенесено; в плане на будущее.
- `Tabs` — как отдельный компонент убран; вкладки реализуются схемами.
- `dataprovider` (фасад) — заменён прямыми PDO-запросами в контроллерах.
- `visuostruct` (AJAX/MIN-структуры старого движка) — заменён на `src/Response/{AjaxStruct,MinStruct}.php`.

## Что появилось нового (не было в старом аудите)

- src/Cli/Commands/UserNewCommand.php — регистрация пользователей через CLI.
- src/Auth/DbAuth.php — авторизация на уровне БД с password_verify.
- src/App/FrontController.php — единый обработчик запросов с middleware.
- src/Ui/{Form,Field,Table,Button,Message,Component}.php — высокоуровневые UI-компоненты.
- src/Admin/Controller/{AliasDb,AliasScheme,Media,Editor,Security,Stub}Controller.php — новые разделы админки.
- src/Admin/Controller/DomainController.php — управление привязками, статусами, приоритетом доменов.
- src/Http/Middleware/{LanguagePath,LanguageCookie,SkinCookie}Middleware.php — I18n и skins.

## Итоги по принципам (P1..P10)

- P1 Домен — 05, 06, 30, 31, 32, 33 (rebuild).
- P2 UI как дерево — 25, 26, 27, 28, 29 (rebuild).
- P3 Регионы и схемы — 34, 35 (rebuild).
- P4 Роутинг файловый — 06, 08, 30, 31 (rebuild).
- P5 Один способ — 02, 03, 04, 15, 24 (rebuild/merge).
- P6 Explicit over implicit — 01, 02, 04, 05 (transfer/rebuild).
- P7 Контракт-first — все rebuild (интерфейсы в src/*Interface.php).
- P8 CLI — 09, 10, 11, 12, 13, 14, 19, 20, 22 (rebuild).
- P9 Безопасность — 07, 10, 13, 15, 18, 21, 23, 33, 36 (security).
- P10 Идемпотентность — 09, 11, 12, 14 (transfer/rebuild).

## Итоги по контрактам (C1..C5)

- C1 DomainResolver — src/Domain/DomainResolverInterface.php + ConfigDomainResolver.php.
- C2 Region — src/Region/RegionInterface.php + RegionRegistry.php.
- C3 Router — src/Routing/RouterInterface.php + FileRouter.php.
- C4 UiNode — src/Ui/Node.php + Element.php + Attrs.php + Component.php.
- C5 Scheme — src/Scheme/SchemeLoader.php + SchemeRenderer.php.

## Итоги по сценариям

- S01–S06 (структура через CLI) — покрыты в `_dev/tests/Unit/Cli/`.
- S07–S11 (рендеринг, 404, maintenance, статус алиаса, доступ к админке) — покрыты в `_dev/tests/Scenarios/`.
- S12–S15 (авторизация, CSRF, привязки доменов) — покрыты в `_dev/tests/Unit/Admin/` и частично сценариями.
- S16–S17 (AJAX JSON) — покрыты в `_dev/tests/Scenarios/AjaxScenarioTest.php`.

## Гейт закрытия карты

Карта считается закрытой, когда:
- каждому исходному NN присвоен статус и путь в `src/`;
- для каждой строки с rebuild/security есть соответствующий рабочий файл в `src/`;
- для drop/merge явно указано, чем заменено (или что функционал убран);
- все 36 файлов `_auditfinal/` покрыты (проверка по NN).

Проверка по NN: 01..36 все присутствуют, каждый в единственной строке. Пропусков нет.

--- КОНЕЦ ФАЙЛА ---