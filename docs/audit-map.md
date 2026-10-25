# Карта покрытия audit -> спецификация

Источник: _devdoc/_auditfinal/ (36 файлов, покрытие 83 файлов _audit).
Назначение: для каждого auditfinal-файла зафиксировать, во что он превращается в новом движке.
Статусы:
- transfer — принцип/контракт переносится
- rebuild — концепция переносится, реализация переписывается
- drop — не переносится
- security — переносится усиленным

Ссылки: P1..P10 — принципы docs/philosophy.md, C1..C5 — контракты docs/contracts.md, S01..S20 — сценарии docs/scenarios.md.

| NN | Файл | Статус | Принципы | Контракты | Сценарии |
|---|---|---|---|---|---|
| 01 | engine_core | transfer | P6 | — | — |
| 02 | engine_services | rebuild | P5, P6 | — | — |
| 03 | engine_db | rebuild | P5 | — | S19 |
| 04 | engine_library | rebuild | P5, P6 | — | — |
| 05 | engine_main_domain | rebuild | P1, P6 | C1 | S11, S13 |
| 06 | engine_language_algoritm | rebuild | P1, P4 | C1, C3 | S16 |
| 07 | engine_security_middleware | security | P9 | — | — |
| 08 | engine_tree | rebuild | P4 | C3 | S11, S12 |
| 09 | corescripts_cli_lib_render | transfer | P8, P10 | — | все CLI |
| 10 | corescripts_lib_data_admin | transfer | P8, P9 | — | — |
| 11 | corescripts_commands_domain_allias | rebuild | P8, P10 | — | S01, S02, S03 |
| 12 | corescripts_commands_block_filler | rebuild | P8, P10 | — | S04, S05, S06, S07 |
| 13 | corescripts_commands_media_page | rebuild | P8, P9, P10 | — | S08, S09 |
| 14 | corescripts_commands_settings_vars | transfer | P8, P10 | — | S20 |
| 15 | www_entry_admin_bootstrap | rebuild | P5, P9 | — | — |
| 16 | www_tests_entry_lib | drop | — | — | — |
| 17 | www_tests_cases | drop | — | — | — |
| 18 | admin_core_router_auth_audit_scan_view | security | P9 | — | — |
| 19 | admin_controllers_domain_allias_base | rebuild | P8, P9 | — | — |
| 20 | admin_controllers_block_filler | rebuild | P8 | — | — |
| 21 | admin_controllers_media_page | security | P9 | — | — |
| 22 | admin_controllers_resource_scheme | rebuild | P8 | — | S10 |
| 23 | admin_controllers_security_settings_stub | security | P9 | — | — |
| 24 | admin_views | rebuild | P5 | — | — |
| 25 | visuo_entry_core | rebuild | P2 | C4 | S11, S14, S15 |
| 26 | visuo_attrs_struct | rebuild | P2 | C4 | S14, S15 |
| 27 | visuoel_base_block_button_message_element | rebuild | P2 | C4 | — |
| 28 | visuoel_field_input | rebuild | P2 | C4 | — |
| 29 | visuoel_form_table_tabs | rebuild | P2 | C4 | — |
| 30 | domain_allias_algoritm | rebuild | P1, P4 | C1, C3 | S11, S12 |
| 31 | domain_algoritm_lang_migrations | rebuild | P1, P4 | C1, C3 | S14, S15, S16, S19 |
| 32 | domain_dataprovider | rebuild | P1 | — | S19 |
| 33 | domain_visuo_core | security | P1, P9 | C1 | S14, S15 |
| 34 | domain_alliasglobal | rebuild | P3 | C2 | S11, S16, S17, S18 |
| 35 | domain_alliasscheme | rebuild | P3 | C5 | S10, S11 |
| 36 | secure_dev_infrastructure | security | P9 | — | — |

## Итоги по статусам

- transfer: 01, 09, 10, 14 — 4 файла
- rebuild: 02, 03, 04, 05, 06, 08, 11, 12, 13, 15, 19, 20, 22, 24, 25, 26, 27, 28, 29, 30, 31, 32, 34, 35 — 24 файла
- security: 07, 18, 21, 23, 33, 36 — 6 файлов
- drop: 16, 17 — 2 файла

Итого: 36 из 36 покрыты.

## Итоги по принципам

- P1 Домен — 05, 06, 30, 31, 32, 33
- P2 UI как дерево — 25, 26, 27, 28, 29
- P3 Регионы и схемы — 34, 35
- P4 Роутинг файловый — 06, 08, 30, 31
- P5 Один способ — 02, 03, 04, 15, 24
- P6 Explicit over implicit — 01, 02, 04, 05
- P7 Контракт-first — неявно во всех rebuild
- P8 CLI — 09, 10, 11, 12, 13, 14, 19, 20, 22
- P9 Безопасность — 07, 10, 13, 15, 18, 21, 23, 33, 36
- P10 Идемпотентность — 09, 11, 12, 14

## Итоги по контрактам

- C1 DomainResolver — 05, 06, 30, 31, 33
- C2 Region — 34
- C3 Router — 08, 30, 31
- C4 UiNode — 25, 26, 27, 28, 29
- C5 Scheme — 35

## Что покрыто сценариями

- S01, S02, S03 — domain/allias lifecycle — 11, 28
- S04, S05, S06, S07 — block/filler lifecycle — 12, 27, 29
- S08, S09 — page lifecycle — 13, 31
- S10 — scheme lifecycle — 35
- S11, S12 — рендер страниц — 25, 30, 34, 35
- S13 — maintenance — 05
- S14, S15 — AJAX-ответы — 25, 26, 31
- S16, S17, S18 — мультиязычность и скин — 34, 80
- S19 — миграции — 31, 78
- S20 — экспорт правил — 14, 33

## Что требует отдельной проработки на Этапе 3

- админка: все сценарии CRUD через admin UI
- безопасность: чек-лист по 07, 18, 21, 23, 33, 36
- производительность: бюджет времени ответа, памяти, размера ядра
- API: версия, обратная совместимость, deprecation-политика

## Гейт Этапа 0

Этап 0 считается закрытым, когда:
- philosophy.md, contracts.md, scenarios.md, audit-map.md существуют
- все 36 файлов _auditfinal имеют статус и привязку
- все 5 контрактов покрывают все сценарии
- все 10 принципов подтверждены источниками