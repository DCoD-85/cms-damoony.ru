# DataProvider & DB Architecture

Дизайн-документ слоя работы с базой данных. Общий roadmap v3.1 (включая AIUI, Orchestrator, standalone-сервер) находится в `_doc/start_new_audit.md` §10.7.

## 1. Цель и границы

**Входит:**
- `src/Db/DataProvider/` — Repository pattern (ядро, owner-only).
- `app/[domain]/[alias]/provider/` — кастомные провайдеры (AI генерирует из шаблонов, dev пишет сложное).
- Introspection: `db:schema`, `db:methods`, `db:describe` (read-only).
- Safe invocation: `db:call`, `db:mutate` (через DataProvider).
- Method generation: `dataprovider:add-method` (templates only).

**Не входит (v3.2+):** AINET POST/HTML, multi-agent hierarchy, визуальный Builder (form/table/select), `CREATE/DROP/ALTER TABLE` через AI, public wizard, Meilisearch.

## 2. DB Architecture

### 2.1. Единая БД + префиксы

    damoony_main
    ├── users, sessions, roles, user_roles, login_attempts  ← глобальные (SSO)
    ├── domains, aliases                                     ← реестр
    ├── d1_www__news, d1_www__doc_articles, ...              ← alias-данные
    ├── d1_doc__articles, d1_doc__sections, ...
    └── d2_www__news, d2_shop__orders, ...

Правило: `d{N}_{alias}__{table}` — alias-данные. Глобальные таблицы (`users`, `sessions`, `audit_log`, …) — без префикса.

### 2.2. Инварианты

1. Всё взаимодействие с БД — только через `DataProvider`. Никакого raw SQL в страницах, регионах, контроллерах.
2. Cross-alias FK — запрещены.
3. `JOIN` разрешён: внутри одного alias; с глобальной таблицей.
4. Транзакции — внутри одного alias.
5. `site.config.domains_aliases_storage` — mapping `{db, prefix}`.

### 2.3. domains_aliases_storage — opt-in

    "domains_aliases_storage": {
      "damoony.ru": {
        "www": { "db": "main", "prefix": "d1_www__" },
        "doc": { "db": "main", "prefix": "d1_doc__" }
      }
    }

`db: "main"` = `engine.config.db.dbname`. Ключ опционален: если отсутствует, `AbstractProvider::physicalTable()` возвращает логическое имя без префикса. Глобальные таблицы не префиксуются никогда.

## 3. DataProvider

### 3.1. Структура

    src/Db/DataProvider/                    ← ЯДРО (owner-only, git)
    ├── AbstractProvider.php                 — query(), physicalTable()
    ├── ProviderInterface.php                 — table(), physicalTable()
    ├── ProviderRegistry.php                 — резолвит (domain, alias, provider): core → custom
    ├── {User,News,Doc,Files,Stats,Audit}Provider.php
    └── Template/
        ├── TemplateInterface.php
        └── SelectWhereTemplate.php, InsertTemplate.php

    app/[domain]/[alias]/provider/          ← ПЕРИФЕРИЯ (AI генерирует, dev пишет, git)
    ├── NewsProvider.php                     — extends Damoony\Db\DataProvider\NewsProvider
    └── CustomLogicProvider.php              — уникальная бизнес-логика

`ProviderRegistry` резолвит: сначала `app/.../provider/`, если нет — `src/Db/DataProvider/`. Кастомный провайдер **расширяет** базовый (наследование), не заменяет.

### 3.2. Контракт

`ProviderInterface`: `table()` — логическое имя, `physicalTable()` — физическое (из конфига).

`AbstractProvider`: `__construct(Paths, Config, domain, alias)`, `protected query(sql, params)`, `protected physicalTable()`.

### 3.3. Кто что пишет

| Что | Кто | Где |
|---|---|---|
| Базовые CRUD (шаблоны) | AI (`dataprovider:add-method`) или dev | `app/.../provider/` |
| Сложная логика | только dev, руками PHP | `app/.../provider/` |
| 6 базовых провайдеров | только owner | `src/Db/DataProvider/` |

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
| 0 | Refactor `bin/aicli.php` → handlers | done |
| 1 | 6 DataProviders | partial (User/News/Files; Doc/Stats/Audit — TBD) |
| 2 | Introspection | done |
| 3 | `db:call`, `db:mutate`, audit_log | done |
| 4.1–4.2 | `select_where`, `insert`, `BlockCodegen` | done |
| 4.4–4.5 | `www/forms.php` + `brain.py` forms | done |
| 4.6–4.7 | AIUI (backend + frontend) | done |
| 4.8 | Остальные 10 шаблонов | pending |
| 5 | Wizard integration (`db:introspect → plan → generate → test`) | pending |

Детали — `git log`, `arch.md` §4/§5, `cli.md` §10.