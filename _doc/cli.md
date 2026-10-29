--- НАЧАЛО ДОКУМЕНТА ---

CLI-команды
Точка входа: php bin/cli.php <command> [--key=value]. Все команды возвращают код возврата: 0 при успехе, 1 при ошибке. Результат — объект Result с полями ok и errors.

CLI и админка — равноправные интерфейсы, но не 100% паритет (MANIFEST.md §3). Только CLI: db:migrate, vars:extract, trash:clear, user:new. Только UI: CodeMirror-редактор, визуальные редакторы.

⚠️ Перед destructive-командами (allias:remove, trash:clear --force, block:remove) — коммит в git. Они перемещают/удаляют файлы.

help
Список всех команд.

bash
php bin/cli.php help
domain:new
Создать домен.

bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
--domain — FQDN (обязательно).

--namespace — PHP-namespace (по умолчанию генерируется из домена: example_ru).

Создаёт папку app/example.ru/, алиас www, .rules.json, обновляет config/site.config. Алиас www создаётся автоматически — отдельный allias:new для него не нужен.

⚠️ Для работы по IP (например 192.168.1.10) добавьте IP как ключ в domains_real вручную или через --domain=192.168.1.10. Порт и trailing-dot нормализуются (stage-3) — 192.168.1.10:8080 сработает.

allias:new
Создать дополнительный алиас.

bash
php bin/cli.php allias:new --domain=example.ru --name=admin
Создаёт app/example.ru/admin/algoritm/ с .rules.json, обновляет site.config.

⚠️ На один IP-хост без DNS работает только один алиас (www) — алиас определяется по Host, не по пути. Для нескольких алиасов нужны разные хосты (DNS/hosts).

allias:remove
Удалить алиас (перемещает папку в карантин).

bash
php bin/cli.php allias:remove --domain=example.ru --name=admin --force
Без --force команда отменяется.

С --force папка app/example.ru/<name>/ переименовывается в app/example.ru/<name>trash<timestamp>/, конфиг очищается.

Карантин остаётся на диске. Удаление — trash:clear или вручную.

block:new
Создать блок (регион шаблона).

bash
php bin/cli.php block:new --domain=example.ru --name=top --type=visual
--name — имя блока (обязательно).

--type — script, visual, mixed (по умолчанию: mixed).

script — только скрипт (не участвует в визуализации, логика).

visual — только визуализация (статичный HTML, контейнер).

mixed — реализация (данные + вёрстка, конечный лист).

Создаёт:

text
app/example.ru/.alliasglobal/
├── blocks.json (обновляется, записывается тип блока)
└── top/
    ├── top.php
    └── top.data.php
⚠️ blocks.json записывается, но не читается RegionLoader (MANIFEST.md §4). Источник истины — папки .alliasglobal/*/.
⚠️ top.data.php не читается движком (§4). Данные — в top.php.

block:remove
Удалить блок.

bash
php bin/cli.php block:remove --domain=example.ru --name=top
Удаляет папку .alliasglobal/top/ и убирает запись из blocks.json.

⚠️ Команда удаляет файлы сразу, без карантина. Коммит в git перед запуском — обязателен.

filler:new
Создать filler (переопределение блока для алиаса).

bash
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
Создаёт в папке блока:

text
app/example.ru/.alliasglobal/top/
├── top.php
├── top.data.php
├── top_www.php (класс alliasglobal_top_www)
└── top_www.data.php
Обновляет site.config (fillers[domain][allias][block] = true). Филлер включается автоматически.

⚠️ top_www.data.php не читается движком (§4). Данные — в top_www.php.

filler:toggle
Включить/выключить filler.

bash
php bin/cli.php filler:toggle --domain=example.ru --allias=www --block=top --value=0
--value=0 — выключить, иначе — включить. Обновляет site.config.

page:new
Создать страницу.

bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
--name — имя страницы (латиница).

--parent — путь родительской страницы относительно algoritm/ (по умолчанию — корень).

Для корневой страницы создаёт:

text
app/example.ru/www/algoritm/
├── .rules.json
├── algoritm.php
└── about/
    ├── .rules.json
    └── algoritm_about.php (класс algoritm_about, namespace домена)
⚠️ Страница не рендерит схему по умолчанию (MANIFEST.md §6.1). Шапки/футера/регионов на такой странице не будет — только Node от default(). См. guide.md §4 (Вариант A — со схемой, Вариант B — без).
✅ default(): null безопасен (возвращает 204 No Content), но лучше возвращать Node или Response.

page:toggle
Переключить флаг страницы.

bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
--path — путь от algoritm/ (пусто = корень).

--key — auth, search или default.

--value — 0 или 1.

Обновляет .rules.json указанной страницы.

scheme:new
Создать схему (каркас страницы).

bash
php bin/cli.php scheme:new --domain=example.ru --name=default
Создаёт default.editor.json (правила для UI) и default.data.php (рендер), обновляет schemes.json. Сканирует .alliasglobal для заполнения fields.

✅ Работает корректно. Визуальный редактор (Drag&Drop) сохраняет вложенность (stage-5).

db:migrate
Применить SQL-миграции. Только CLI.

bash
php bin/cli.php db:migrate
Читает .sql из migrations/, применяет по порядку, пишет в таблицу migrations. Идемпотентна — повторный запуск не дублирует миграции. Поддерживает SQLite и MariaDB/MySQL.

vars:extract
Перенести свойства available, auth, search, default из .php в .data.json. Только CLI.

bash
php bin/cli.php vars:extract --domain=example.ru --allias=www
--dry — показать план без записи.

--no-clean — не удалять свойства из исходных .php.

Идемпотентна — повторный запуск не перезаписывает .data.json.

⚠️ Назначение команды — наследие миграции со старого формата. В текущем движке .data.json не читается (аналогично .data.php блоков, MANIFEST.md §4). Перед запуском — проверьте, нужна ли она вообще в вашем проекте.

admin:passwd
Задать логин и пароль администратора (для входа в админку).

bash
php bin/cli.php admin:passwd --name=admin --password=secret
Записывает login и password_hash в config/admin.config. Смените дефолтный admin / secret перед деплоем.

user:new
Создать пользователя сайта (для страниц с "auth": true). Только CLI.

bash
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
--login — 3–32 символа, латиница и цифры.

--email — валидный email.

--password — минимум 6 символов.

Записывает пользователя с хешированным паролем в таблицу users.

⚠️ Это не администратор админки. Админка использует отдельную авторизацию (admin:passwd, config/admin.config). Не путайте (MANIFEST.md §Auth).

trash:clear
Очистить карантин (папки *trash<timestamp>). Только CLI.

bash
php bin/cli.php trash:clear
По умолчанию (dry-run) — показывает список папок в карантине.

--force — удалить всё.

--domain=example.ru — только для конкретного домена.

--older=7d — только старше 7 дней (формат: 7d, 24h, 30m).

Безопасна по умолчанию (только показывает список). Удаляет безвозвратно — коммит в git перед --force.

structure:check
Проверить целостность структуры доменов и алиасов.

bash
php bin/cli.php structure:check
Проверяет наличие .rules.json, algoritm.php, схем, глобальных блоков, стыковку филлер↔слот, а также `.edit.json ↔ PHP` и `schemes.json ↔ файлы` (stage-5).

Выводит список ошибок или сообщение об успехе.

structure:repair
Умное восстановление структуры домена (восстанавливает удаленные алиасы, синхронизирует JSON-манифесты).

bash
php bin/cli.php structure:repair --domain=example.ru
Сканирует папку домена и:
- Восстанавливает папки удаленных алиасов (создает algoritm/ и algoritm.php).
- Добавляет в schemes.json записи о существующих .data.php.
- Добавляет в blocks.json записи о существующих папках блоков.

⚠️ Не трогает страницы и настройки в site.config. Корректно обрабатывает пустые алиасы ("" -> www).
Для проверки без записи используйте structure:check. Подробности — structure_repair.md.

Общие флаги
--dry — предпросмотр без записи (vars:extract, trash:clear).

--force — без подтверждения (allias:remove, trash:clear).

Идемпотентность
db:migrate — повторный запуск не дублирует миграции.

vars:extract — повторный запуск не перезаписывает .data.json.

*:new — возвращают ошибку, если объект уже существует.

trash:clear — безопасен, по умолчанию только показывает список.

structure:repair — безопасен, проверяет наличие перед созданием.

Чего в CLI нет (только UI)
CodeMirror-редактор (/admin/?p=editor).

Визуальные редакторы блоков, схем, страниц.

/admin/?p=profile — смена пароля админки, логи, архивирование.

Управление банами (/admin/?p=security).

Для этих операций — админка (admin.md).

Что делать при ошибке
structure:check — базовая диагностика.

structure:repair — умное восстановление структуры.

Проверить config/site.config (валидный JSON, self-mapping в domains_real).

Проверить config/engine.config (доступы к БД).

Логи — var/ (если настроены) или _dev/ (dev).

Если ошибка в визуальном редакторе — см. admin.md §«Что НЕ работает».

Подробнее о состоянии проекта — MANIFEST.md §7 (Backlog). О жизненном цикле — architecture.md.

--- КОНЕЦ ДОКУМЕНТА ---