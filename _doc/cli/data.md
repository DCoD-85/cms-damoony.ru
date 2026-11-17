# CLI: база данных и провайдеры

`bin/cli.php` работает с БД через `DataProvider`. Проектирование схем — отдельный вход
`bin/dbcli.php` (см. ниже и `_doc/dbcli.md`).

## 1. Introspection (read-only)

| Команда | Что отдаёт |
|---|---|
| `db:schema [--alias=www]` | таблицы и колонки проекта (`information_schema` / `sqlite_master`) |
| `db:methods --provider=P` | публичные бизнес-методы провайдера (Reflection) |
| `db:describe --provider=P --method=M` | сигнатура, типы, doc-блок |

Таблицы фильтруются по префиксу алиаса; глобальные (`users`, `sessions`, `roles`,
`user_roles`, `login_attempts`, `migrations`, `audit_log`) видны всегда.

## 2. Вызов

| Команда | Требования |
|---|---|
| `db:call --provider=P --method=M [--params='{}' \| --params_b64=…]` | read-only, whitelist ядра |
| `db:mutate … --force=1 [--source=cli\|ai] [--role=…]` | write, `--force=1` обязателен, пишет `audit_log` |

Whitelist ядра (по имени провайдера):

- чтение: `user` — `by_login, by_id, exists`; `news` — `recent, by_slug, by_id, search_like`;
  `files` — `all_active, by_slug, by_id`; `audit` — `recent, by_entity`;
  `form_submission` — `recent, by_form`;
- запись: `user` — `create`; `news` — `create, update, delete, publish`;
  `files` — `create, update_filesize, increment_downloads, delete`;
  `form_submission` — `delete, delete_all`.

Кастомные провайдеры (`app/{folder}/{alias}/.dbprovider/`) вызываются без whitelist —
ответственность на разработчике.

Параметры приводятся к типам сигнатуры (`string`, `int`, `bool`, `array`); неизвестные
имена параметров и несоответствие типов отклоняются. Метод с единственным параметром-массивом
получает `--params` целиком.

**Запрещено всем:** `CREATE/DROP/ALTER TABLE`, `TRUNCATE`, `GRANT/REVOKE`.
Миграции — только `db:migrate` от владельца.

## 3. Аудит

`db:mutate` пишет в `audit_log`: `domain`, `alias_name`, `provider`, `method`, `params_json`,
`result_ok`, `error_text`, `source`, `role_name`. Ошибка записи аудита не отменяет мутацию —
выводится warning. Журнал запусков CLI (`var/log/cli/`) — отдельная сущность.

## 4. Префиксы таблиц

Источник — `domains_app.{key}.aliases_storage.{alias}.prefix` (см. `config.md`).
Провайдер получает префикс через `AbstractProvider`; в CLI его собирает
`ProviderInvoker::providerConfig()`. После перевода движка на `SiteConfig` этот мост уходит
(фаза 2).

## 5. Миграции

`db:migrate [--dir=migrations/sub]` — применяет SQL из `migrations/`, идемпотентно
(таблица `migrations` хранит версии). `--dir` ограничен подкаталогами `migrations/`.

## 6. `dataprovider:add-method`

`--provider=P --template=select_where|insert (--args='{}' | --args_b64=…)`

Генерирует метод из шаблона (PDO named params, без конкатенации значений) и дописывает его
в `app/{folder}/{alias}/.dbprovider/{P}Provider.php` (создаёт файл с boilerplate при
необходимости). Отказывает, если метод с таким именем уже есть. **AI не пишет PHP** —
используются только шаблоны.

## 7. `dbcli` (проектирование схем)

`bin/dbcli.php` — отдельный вход: JSON-схемы в `.dbglobal`/`.dblocal`, генерация SQL-миграций
и PHP-провайдеров. См. `_doc/dbcli.md`. На схеме `domains_app` `dbcli` пока не работает и
падает с явной ошибкой (фаза 2).
