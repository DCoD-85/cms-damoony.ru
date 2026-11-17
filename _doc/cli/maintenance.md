# CLI: инициализация, диагностика, карантин, бэкапы

## 1. Инициализация

```
php bin/cli.php cli:init --name=<кто>          # один раз, создаёт var/cli.identity
php bin/cli.php admin:passwd --name=admin --password=<сильный>   # вход в админку
```

- `cli:init` — единственная команда, доступная без идентификатора; `--force` перезаписывает;
- `admin:passwd` пишет `config/admin.config` (login, `password_hash`, `default_lang`, `default_role`).

## 2. Журнал запусков

`var/log/cli/YYYY-MM-DD.log`, JSONL, одна строка на запуск; секреты редактируются.
Ограничения прав и отключение CLI — через `var/cli.identity` (см. `README.md` §5–6).

## 3. Диагностика структуры

`system:scan` — сверка с эталоном: что лишнее в корне/`www/`, чего обязательного нет.
Возвращает `{ideal, garbage_root, garbage_www, missing}`; `ideal=false` — проект отклоняется
от эталона.

## 4. Уборка

`system:clean [--force] [--sessions] [--trash]` — удаляет весь карантин и брошенные
сессии/визарды. Без `--force` печатает план.

`trash:clear [--force] [--older=7d\|24h\|30m] [--domain=X]` — очистка карантина с фильтрами.
По умолчанию dry-run. Удаление — `Fs::rmrf` (symlink-safe).

### Виды карантина (все находятся сканером)

| Тип | Путь |
|---|---|
| проект | `app/{folder}_trash_{ts}/` |
| алиас | `app/{folder}/{alias}_trash_{ts}/` |
| схема | `app/{folder}/.schemesglobal/{name}_trash_{ts}/` |
| блок | `app/{folder}/.blocksglobal/{name}_trash_{ts}/` |
| сессия | `app/_trash_sess_{tab}_{folder}_{ts}/` |
| бэкап/восстановление | `app/{folder}_trash_restore_{ts}/` |
| media | `www/media/.trash/*` |
| контент | `content/.trash/*` |
| языки | `var/trash/lang_*` |

Новый тип карантина обязан попасть в `TrashScanner` — иначе он не будет чиститься.

## 5. Бэкапы

| Команда | Что делает |
|---|---|
| `backup:create --domain=X` | ZIP в `var/backups/`: `app/{folder}/`, `www/media/{folder}/`, `db.sql` |
| `backup:restore --domain=X --file=… --force` | распаковка, импорт БД, файлы проекта в карантин, копирование из архива |

Ограничения и гарантии:

- только MySQL/MariaDB (`driver=mysql`), иначе явный отказ;
- дамп/импорт — `mysqldump`/`mysql` (на Windows ищется локальный бинарь MariaDB);
- упаковка/распаковка: `ZipArchive`, при его отсутствии — `PharData`;
- при ошибке временный каталог удаляется, недоделанный ZIP не остаётся;
- старые файлы уходят в карантин с маркером `_trash_restore_`, а не удаляются.

## 6. Прочее

| Команда | Назначение |
|---|---|
| `api:reflect` | IDE-стабы `Builder/Element/Attrs/Text` в `_dev/api.stub.php` |
| `migrate:page-rules [--domain=X] [--dry]` | легаси `.rules.json` → `algoritm_{name}.rules.json`, идемпотентно |
| `user:new --login=… --email=… --password=… [--role=user\|admin] [--update]` | пользователь сайта (таблица `users`, глобальная) |
| `site:config:migrate` | перенос `site.config.old` → схема `domains_app` |

## 7. Работа без БД

CLI не требует доступной БД для файловых команд. Если БД не сконфигурирована
(`config/engine.config → db` отсутствует), `db:*` и `user:new` отказывают с явным текстом,
а не падают «белым экраном».
