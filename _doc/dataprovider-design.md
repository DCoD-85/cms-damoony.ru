# DataProvider & DB Architecture

Дизайн-документ слоя работы с базой данных. Общий roadmap v3.1 (включая AIUI, Orchestrator, standalone-сервер) находится в `_doc/start_new_audit.md` §10.7.

## 1. Цель и границы

**Входит:**
- `src/Data/Db/DataProvider/` — Repository pattern (ядро, owner-only).
- `app/[domain]/[alias]/.dbprovider/` — кастомные провайдеры (AI генерирует из шаблонов, dev пишет сложное).
- Introspection: `db:schema`, `db:methods`, `db:describe` (read-only).
- Safe invocation: `db:call`, `db:mutate` (через DataProvider).
- Method generation: `dataprovider:add-method` (templates only).

**Не входит (v3.2+):** AINET POST/HTML, multi-agent hierarchy, визуальный Builder (form/table/select), `CREATE/DROP/ALTER TABLE` через AI, public wizard, Meilisearch.

## 2. DB Architecture

### 2.1. Структура v4
- `.dbglobal/schema.json` — схема БД домена (глобальные таблицы).
- `.dblocal/schema.json` — схема БД алиаса (локальные таблицы).
- `.dbprovider/` — PHP-классы провайдеров (генерируются `dbcli`).

### 2.2. Инварианты
1. Всё взаимодействие с БД — только через `DataProvider`.
2. Запись — только через `db:mutate` (с `--force`). Read-only — `db:call`.
3. Миграции — `db:migrate`. Никаких `ALTER TABLE` через AI.
4. Cross-alias FK — запрещены.

## 3. DataProvider

### 3.1. Структура

    src/Data/Db/DataProvider/                    ← ЯДРО (owner-only, git)
    ├── AbstractProvider.php                 — query(), physicalTable()
    ├── ProviderInterface.php                 — table(), physicalTable()
    ├── ProviderRegistry.php                 — резолвит (domain, alias, provider): core → custom
    ├── ProviderResolver.php                 — 3-уровневый поиск (alias → domain → core)
    ├── BasicCrudProvider.php                — generic CRUD по JSON-схеме
    ├── DynamicProvider.php                  — динамический провайдер
    ├── FormSubmissionProvider.php           — form_submissions (www/forms.php)
    ├── UserProvider.php, NewsProvider.php, FilesProvider.php, AuditProvider.php
    └── Template/
        ├── TemplateInterface.php
        └── SelectWhereTemplate.php, InsertTemplate.php

    app/[domain]/[alias]/.dbprovider/          ← ПЕРИФЕРИЯ (AI генерирует, dev пишет, git)
    ├── NewsProvider.php                     — extends Damoony\Data\Db\DataProvider\NewsProvider
    └── CustomLogicProvider.php              — уникальная бизнес-логика

`ProviderResolver` ищет провайдеров по 3 уровням (Scope):
1. **Alias Scope:** `app/[domain]/[alias]/.dbprovider/` (уникальная логика раздела).
2. **Domain Scope:** `app/[domain]/.dbprovider/` (общая логика для всех алиасов домена).
3. **Core Scope:** `src/Data/Db/DataProvider/` (системные провайдеры).
Также поддерживается **кросс-алиасный доступ** в формате `--provider=doc.articles`, что позволяет одному алиасу использовать провайдер другого.

### 3.2. Контракт

`ProviderInterface`: `table()` — логическое имя, `physicalTable()` — физическое (из конфига).

`AbstractProvider`: `__construct(Paths, Config, domain, alias)`, `protected query(sql, params)`, `protected physicalTable()`.

### 3.3. Кто что пишет

| Что | Кто | Где |
|---|---|---|
| Базовые CRUD (шаблоны) | AI (`dataprovider:add-method`) или dev | `app/.../.dbprovider/` |
| Сложная логика | только dev, руками PHP | `app/.../.dbprovider/` |
| 6 базовых провайдеров | только owner | `src/Data/Db/DataProvider/` |

AI не пишет PHP: вызывает готовые методы (`db:call`, `db:mutate`), генерирует новые из шаблонов (`dataprovider:add-method`).

## 4. Templates

Реализованы: `select_where`, `insert` (PDO named params, без конкатенации значений).

Pending (Phase 4.8): `select_by`, `select_all`, `update`, `delete`, `count`, `exists`, `aggregate`, `join_select`, `group_by_count`, `sum_by`.

## 5. API (bin/aicli.php)

### 5.1. Команды (`bin/cli.php`)

- `db:schema --domain --alias` — таблицы + колонки.
- `db:methods --domain --alias --provider` — методы провайдера.
- `db:describe --domain --alias --provider --method` — сигнатура.
- `db:call --domain --alias --provider --method --params='{}'` — read-only.
- `db:mutate … --force=1` — write + audit_log.
- `dataprovider:add-method --template=… --args='{}'` — генерация.

Per-command флаги — `_doc/cli.md` §10.

### 5.2. Роли (`bin/aicli.php`)

| Команда | Роль |
|---|---|
| `db:schema`, `db:methods`, `db:describe` | inspector |
| `db:call` | inspector, executor, reporter |
| `db:mutate` | executor (`--force`) |
| `dataprovider:add-method` | architect |

`CREATE/DROP/ALTER TABLE` — запрещены всем. Миграции — `db:migrate` от владельца.

### 5.3. Формат запроса / ответа

    {"command": "db:call", "role": "inspector",
     "args": {"domain": "…", "alias": "www", "provider": "news", "method": "recent", "params": {"limit": 10}}}

    {"ok": true, "data": {"rows": […], "count": 5}, "error": "", "hint": "", "role": "inspector", "next": […]}

## 6. Статус фаз
| Phase | Что | Статус |
|---|---|---|
| 1 | 6 DataProviders | done |
| 2 | Introspection | done |
| 3 | `db:call`, `db:mutate`, audit_log | done |
| 4.1–4.2 | `select_where`, `insert`, `BlockCodegen` | done |
| 4.8 | Остальные шаблоны | pending |
| 5 | Wizard integration | pending |

Детали — `git log`, `arch.md` §5.