--- НАЧАЛО ФАЙЛА---

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
- `--logic` — версия движка (по умолчанию `alpha.0.0`).

Создаёт папку домена, папку алиаса `www`, файл `.rules.json` и обновляет `config/site.config`.

## allias:new

Создать алиас для домена.

```bash
php bin/cli.php allias:new --domain=example.ru --name=admin
```

Создаёт структуру алиаса (папку `algoritm` и `.rules.json`) и обновляет `config/site.config`.

## allias:remove

Удалить алиас (перемещает папку в карантин).

```bash
php bin/cli.php allias:remove --domain=example.ru --name=admin --force
```

Без `--force` команда отменяется. С `--force` папка переименовывается в `_trash_<timestamp>`, конфиг очищается, карантин удаляется.

## block:new

Создать блок (регион шаблона).

```bash
php bin/cli.php block:new --domain=example.ru --name=custom
```

Создаёт файлы `custom.php` и `custom.data.php` в папке `alliasglobal`, обновляет `blocks.json`.

## block:remove

Удалить блок.

```bash
php bin/cli.php block:remove --domain=example.ru --name=custom
```

Удаляет папку блока и убирает запись из `blocks.json`.

## filler:new

Создать filler (переопределение блока для конкретного алиаса).

```bash
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
```

Создаёт `top_www.php` и `top_www.data.php`, обновляет `site.config` (включает filler).

## filler:toggle

Включить или выключить filler.

```bash
php bin/cli.php filler:toggle --domain=example.ru --allias=www --block=top --value=0
```

`--value=0` — выключить, `--value=1` (или любой другой символ) — включить. Обновляет `site.config`.

## page:new

Создать страницу.

```bash
php bin/cli.php page:new --domain=example.ru --allias=www --parent=index --name=about
```

- `--parent` — путь родительской страницы (пусто = корень).
- `--name` — имя новой страницы (латиница).

Создаёт `algoritm_about.php`, `.rules.json` и прописывает страницу в `.rules.json` родителя.

## page:toggle

Переключить флаг страницы.

```bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
```

- `--key` — `auth`, `search` или `default`.
- `--value` — `0` или `1`.

Обновляет `.rules.json` указанной страницы.

## scheme:new

Создать схему (каркас страницы).

```bash
php bin/cli.php scheme:new --domain=example.ru --name=visuo
```

Создаёт `visuo.data.php` в папке `alliasscheme` и обновляет `schemes.json`.

## db:migrate

Применить SQL-миграции.

```bash
php bin/cli.php db:migrate
```

Читает `.sql` файлы из папки `migrations/`, применяет их и записывает в таблицу `migrations`. Команда идемпотентна (не применяет одно и то же дважды).

## vars:extract

Перенести свойства `$available`, `$auth`, `$search`, `$default` из `.php` файлов в `.data.json`.

```bash
php bin/cli.php vars:extract --domain=example.ru --allias=www
```

- `--dry` — показать план без реальной записи.
- `--no-clean` — не удалять свойства из исходных `.php` файлов.

## admin:passwd

Задать логин и пароль администратора (для входа в панель управления).

```bash
php bin/cli.php admin:passwd --name=admin --password=secret
```

Записывает `login` и `password_hash` в `config/admin.config`.

## user:new

Создать пользователя на сайте (для авторизации на страницах с `auth: true`).

```bash
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
```

- `--login` — логин (3-32 символа, латиница и цифры).
- `--email` — валидный email.
- `--password` — пароль (минимум 6 символов).

Записывает пользователя с хешированным паролем в таблицу `users` базы данных SQLite.

## Общие флаги

- `--dry` — режим предпросмотра без записи (для `vars:extract`).
- `--force` — без подтверждения (для `allias:remove`).
- `--logic=alpha.0.0` — указать версию движка (по умолчанию `alpha.0.0`).

## Идемпотентность

- `db:migrate` — повторный запуск не дублирует миграции.
- `vars:extract` — повторный запуск не перезаписывает `.data.json`.
- `*:new` — возвращают ошибку, если объект уже существует.

--- КОНЕЦ ФАЙЛА ---