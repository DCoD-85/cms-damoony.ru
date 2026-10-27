--- НАЧАЛО ФАЙЛА _doc/cli.md ---
# CLI-команды

Точка входа: `php bin/cli.php <command> [--key=value]`

Все команды возвращают код возврата: 0 при успехе, 1 при ошибке.

## help

```bash
php bin/cli.php help
```
Выводит список всех доступных команд.

## domain:new

Создать домен.

```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

- `--domain` — FQDN домена (обязательно).
- `--namespace` — PHP-неймспейс (по умолчанию генерируется из домена: `example_ru`).

Создаёт:

```
app/example.ru/
├── .alliasglobal/                     (пусто, заполняется block:new)
├── .alliasscheme/                     (пусто, заполняется scheme:new)
└── www/
    └── algoritm/
        └── .rules.json                {"available": [""]}
```

и обновляет `config/site.config` (`domains_real`, `domains_available`, `domains_namespace`, `alliases_real`).

**Алиас `www` создаётся автоматически.** Отдельный `allias:new` нужен только для дополнительных алиасов (`doc`, `admin`, …).

## allias:new

Создать дополнительный алиас для домена.

```bash
php bin/cli.php allias:new --domain=example.ru --name=admin
```

Создаёт `app/example.ru/admin/algoritm/` с `.rules.json` и обновляет `config/site.config` (`alliases_real`).

## allias:remove

Удалить алиас (перемещает папку в карантин).

```bash
php bin/cli.php allias:remove --domain=example.ru --name=admin --force
```

- Без `--force` команда отменяется.
- С `--force` папка `app/example.ru/<name>/` переименовывается в `app/example.ru/<name>_trash_<timestamp>/`, конфиг очищается.
- **Карантин остаётся на диске.** Удаление — вручную, после проверки, что новый конфиг корректен (P10).

## block:new

Создать блок (регион шаблона).

```bash
php bin/cli.php block:new --domain=example.ru --name=top
```

Создаёт:

```
app/example.ru/.alliasglobal/
├── blocks.json                             (обновляется)
└── top/
    ├── top.php                             (готовый RegionInterface)
    └── top.data.php
```

Сгенерированный `top.php` содержит namespace домена, `implements RegionInterface`, методы `name() / enabled() / render()`.

## block:remove

Удалить блок.

```bash
php bin/cli.php block:remove --domain=example.ru --name=top
```

Удаляет папку `.alliasglobal/top/` и убирает запись из `blocks.json`.

## filler:new

Создать filler (переопределение блока для конкретного алиаса).

```bash
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
```

Создаёт в папке блока:

```
app/example.ru/.alliasglobal/top/
├── top.php
├── top.data.php
├── top_www.php                             (класс alliasglobal_top_www implements RegionInterface)
└── top_www.data.php
```

и обновляет `config/site.config` (`fillers[domain][allias][block] = true`).

## filler:toggle

Включить или выключить filler.

```bash
php bin/cli.php filler:toggle --domain=example.ru --allias=www --block=top --value=0
```

`--value=0` — выключить, иначе — включить. Обновляет `config/site.config`.

## page:new

Создать страницу.

```bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
```

- `--name` — имя новой страницы (латиница).
- `--parent` — путь родительской страницы **относительно `algoritm/`** (по умолчанию — пусто = корень).

Для корневой страницы создаёт:

```
app/example.ru/www/algoritm/
├── .rules.json                  (available += ["about"])
├── algoritm.php
└── about/
    ├── .rules.json              ({"available": ["about"]})
    └── algoritm_about.php       (класс algoritm_about, namespace домена)
```

Для вложенной страницы (`--parent=about --name=team`):

```
algoritm/about/team/
├── .rules.json
└── algoritm_about_team.php      (класс algoritm_about_team)
```

Класс генерируется с `namespace <домен>\<allias>` и наследует `Algoritm`.

## page:toggle

Переключить флаг страницы.

```bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
```

- `--path` — путь от `algoritm/` (пусто = корень).
- `--key` — `auth`, `search` или `default`.
- `--value` — `0` или `1`.

Обновляет `.rules.json` указанной страницы (создаёт файл, если его нет).

## scheme:new

Создать схему (каркас страницы).

```bash
php bin/cli.php scheme:new --domain=example.ru --name=default
```

Создаёт `app/example.ru/.alliasscheme/default.data.php` и обновляет `schemes.json`.

## db:migrate

Применить SQL-миграции.

```bash
php bin/cli.php db:migrate
```

Читает `.sql` из `migrations/`, применяет по порядку, пишет в таблицу `migrations`. Идемпотентна.

## vars:extract

Перенести свойства `$available`, `$auth`, `$search`, `$default` из `.php` в `.data.json`.

```bash
php bin/cli.php vars:extract --domain=example.ru --allias=www
```

- `--dry` — показать план без записи.
- `--no-clean` — не удалять свойства из исходных `.php`.

## admin:passwd

Задать логин и пароль администратора.

```bash
php bin/cli.php admin:passwd --name=admin --password=secret
```

Записывает `login` и `password_hash` в `config/admin.config`.

## user:new

Создать пользователя сайта (для страниц с `auth: true`).

```bash
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
```

- `--login` — 3-32 символа, латиница и цифры.
- `--email` — валидный email.
- `--password` — минимум 6 символов.

Записывает пользователя с хешированным паролем в таблицу `users`.

## Общие флаги

- `--dry` — предпросмотр без записи (`vars:extract`).
- `--force` — без подтверждения (`allias:remove`).

## Идемпотентность

- `db:migrate` — повторный запуск не дублирует миграции.
- `vars:extract` — повторный запуск не перезаписывает `.data.json`.
- `*:new` — возвращают ошибку, если объект уже существует.
--- КОНЕЦ ФАЙЛА _doc/cli.md ---