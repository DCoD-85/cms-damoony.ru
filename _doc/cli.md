--- НАЧАЛО ФАЙЛА _doc/cli.md ---

CLI-команды

Точка входа: php bin/cli.php <command> [--key=value]
Все команды возвращают код возврата: 0 при успехе, 1 при ошибке.

help
Выводит список всех доступных команд.
php bin/cli.php help

domain:new
Создать домен.
php bin/cli.php domain:new --domain=example.ru --namespace=example

--domain — FQDN домена (обязательно).

--namespace — PHP-неймспейс (по умолчанию генерируется из домена: example_ru).

Создаёт папку домена app/example.ru/, папку алиаса www, файл .rules.json и обновляет config/site.config.
Алиас www создаётся автоматически. Отдельный allias:new нужен только для дополнительных алиасов (doc, admin, …).

allias:new
Создать дополнительный алиас для домена.
php bin/cli.php allias:new --domain=example.ru --name=admin
Создаёт app/example.ru/admin/algoritm/ с .rules.json и обновляет config/site.config.

allias:remove
Удалить алиас (перемещает папку в карантин).
php bin/cli.php allias:remove --domain=example.ru --name=admin --force

Без --force команда отменяется.

С --force папка app/example.ru/<name>/ переименовывается в app/example.ru/<name>trash<timestamp>/, конфиг очищается.

Карантин остаётся на диске. Удаление — командой trash:clear или вручную.

block:new
Создать блок (регион шаблона).
php bin/cli.php block:new --domain=example.ru --name=top
Создаёт:
app/example.ru/.alliasglobal/
├── blocks.json (обновляется)
└── top/
├── top.php (готовый RegionInterface)
└── top.data.php
Сгенерированный top.php содержит namespace домена, implements RegionInterface, методы name() / enabled() / render().

block:remove
Удалить блок.
php bin/cli.php block:remove --domain=example.ru --name=top
Удаляет папку .alliasglobal/top/ и убирает запись из blocks.json.

filler:new
Создать filler (переопределение блока для конкретного алиаса).
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
Создаёт в папке блока:
app/example.ru/.alliasglobal/top/
├── top.php
├── top.data.php
├── top_www.php (класс alliasglobal_top_www implements RegionInterface)
└── top_www.data.php
и обновляет config/site.config (fillers[domain][allias][block] = true).

filler:toggle
Включить или выключить filler.
php bin/cli.php filler:toggle --domain=example.ru --allias=www --block=top --value=0
--value=0 — выключить, иначе — включить. Обновляет config/site.config.

page:new
Создать страницу.
php bin/cli.php page:new --domain=example.ru --allias=www --name=about

--name — имя новой страницы (латиница).

--parent — путь родительской страницы относительно algoritm/ (по умолчанию — пусто = корень).

Для корневой страницы создаёт:
app/example.ru/www/algoritm/
├── .rules.json (available += ["about"])
├── algoritm.php
└── about/
├── .rules.json ({"available": ["about"]})
└── algoritm_about.php (класс algoritm_about, namespace домена)
Для вложенной страницы (--parent=about --name=team) создаёт папку team внутри about.

page:toggle
Переключить флаг страницы.
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1

--path — путь от algoritm/ (пусто = корень).

--key — auth, search или default.

--value — 0 или 1.
Обновляет .rules.json указанной страницы.

scheme:new
Создать схему (каркас страницы).
php bin/cli.php scheme:new --domain=example.ru --name=default
Создаёт app/example.ru/.alliasscheme/default.data.php и обновляет schemes.json.

db:migrate
Применить SQL-миграции.
php bin/cli.php db:migrate
Читает .sql из migrations/, применяет по порядку, пишет в таблицу migrations. Идемпотентна. Поддерживает SQLite и MariaDB/MySQL.

vars:extract
Перенести свойства 
a
v
a
i
l
a
b
l
e
,
available,auth, 
s
e
a
r
c
h
,
search,default из .php в .data.json.
php bin/cli.php vars:extract --domain=example.ru --allias=www

--dry — показать план без записи.

--no-clean — не удалять свойства из исходных .php.

admin:passwd
Задать логин и пароль администратора (для входа в панель управления).
php bin/cli.php admin:passwd --name=admin --password=secret
Записывает login и password_hash в config/admin.config.

user:new
Создать пользователя на сайте (для авторизации на страницах с auth: true).
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret

--login — 3-32 символа, латиница и цифры.

--email — валидный email.

--password — минимум 6 символов.
Записывает пользователя с хешированным паролем в таблицу users.

trash:clear
Очистить карантин (папки *trash<timestamp>).
php bin/cli.php trash:clear

По умолчанию (dry-run) показывает список папок в карантине.

--force — удалить всё.

--domain=example.ru — очистить только для конкретного домена.

--older=7d — удалить только старше 7 дней (формат: 7d, 24h, 30m).

structure:check
Проверить целостность структуры доменов и алиасов (наличие .rules.json, algoritm.php, схем, блоков).
php bin/cli.php structure:check
Выводит список ошибок (если есть) или сообщение об успехе. Команду можно использовать в Cron для фонового мониторинга здоровья системы.

Общие флаги

--dry — предпросмотр без записи (vars:extract, trash:clear).

--force — без подтверждения (allias:remove, trash:clear).

Идемпотентность

db:migrate — повторный запуск не дублирует миграции.

vars:extract — повторный запуск не перезаписывает .data.json.

*:new — возвращают ошибку, если объект уже существует.

trash:clear — безопасен, по умолчанию только показывает список.

--- КОНЕЦ ФАЙЛА _doc/cli.md ---