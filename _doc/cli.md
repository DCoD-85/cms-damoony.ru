--- НАЧАЛО ДОКУМЕНТА ---

CLI-команды
Точка входа: php bin/cli.php <command> [--key=value]. Все команды возвращают код возврата: 0 при успехе, 1 при ошибке. Результат — объект Result с полями ok и errors.

Философия CLI-First: CLI является единственным источником истины для файловых операций. Админка и AI-агенты — это лишь обертки над этими командами. 

Правило 3 файлов: Все сущности (Страницы, Блоки, Схемы) состоят из трех файлов: {name}.php, {name}.rules.json, {name}.editor.json.

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
Создаёт папку app/example.ru/, алиас www, .rules.json, обновляет config/site.config. Алиас www создаётся автоматически.
⚠️ Порт и trailing-dot нормализуются (stage-3) — 192.168.1.10:8080 сработает.

allias:new
Создать дополнительный алиас.
bash
php bin/cli.php allias:new --domain=example.ru --name=admin
Создаёт app/example.ru/admin/algoritm/ с .rules.json, обновляет site.config.
⚠️ На один IP-хост без DNS работает только один алиас (www).

allias:remove
Удалить алиас (перемещает папку в карантин).
bash
php bin/cli.php allias:remove --domain=example.ru --name=admin --force
Без --force команда отменяется. С --force папка переименовывается в <name>_trash_<timestamp>/, конфиг очищается. Удаление — trash:clear.

block:new
Создать блок (регион шаблона).
bash
php bin/cli.php block:new --domain=example.ru --name=top --type=visual
--name — имя блока (обязательно).
--type — script, visual, mixed (по умолчанию: mixed).
Создаёт три файла в .alliasglobal/top/:
- top.php (код класса)
- top.rules.json (содержит тип блока и статус)
- top.editor.json (пустой файл для UI)

block:remove
Удалить блок.
bash
php bin/cli.php block:remove --domain=example.ru --name=top
Удаляет папку .alliasglobal/top/ целиком.
⚠️ Команда удаляет файлы сразу, без карантина. Коммит в git перед запуском — обязателен. (В коде есть защита от Path Traversal).

filler:new
Создать filler (переопределение блока для алиаса).
bash
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
Создаёт в папке блока:
- top_www.php (класс)
- top_www.rules.json (настройки)
- top_www.editor.json (данные UI)
Обновляет site.config (fillers[domain][allias][block] = true).

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
--parent — путь родительской страницы (по умолчанию — корень).
Создаёт три файла в about/:
- algoritm_about.php
- algoritm_about.rules.json
- algoritm_about.editor.json
⚠️ Страница не рендерит схему по умолчанию (MANIFEST.md §6.1). Вызов SchemeRenderer нужно добавить вручную в код (см. guide.md §4).

page:toggle
Переключить флаг страницы.
bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
--path — путь от algoritm/ (пусто = корень).
--key — auth, search или default.
--value — 0 или 1.
Обновляет algoritm_{name}.rules.json указанной страницы.

scheme:new
Создать схему (каркас страницы).
bash
php bin/cli.php scheme:new --domain=example.ru --name=default
Создаёт три файла в .alliasscheme/:
- default.php (рендер)
- default.rules.json (настройки)
- default.editor.json (правила для Drag&Drop UI)
Сканирует .alliasglobal для заполнения доступных блоков в editor.json.

scheme:copy
Копировать схему.
bash
php bin/cli.php scheme:copy --domain=example.ru --name=default
Копирует все три файла схемы в default_copy.php, default_copy.rules.json, default_copy.editor.json.

scheme:remove
Удалить схему.
bash
php bin/cli.php scheme:remove --domain=example.ru --name=default
Удаляет все три файла схемы из .alliasscheme/.

scheme:save
Сохранить визуальную схему (используется админкой).
bash
php bin/cli.php scheme:save --domain=example.ru --name=default --scheme_json='{"children":[]}'
Обновляет editor.json и компилирует чистый PHP-массив в default.php (через SchemeCompiler).

db:migrate
Применить SQL-миграции. Только CLI.
bash
php bin/cli.php db:migrate
Идемпотентна. Поддерживает SQLite и MariaDB/MySQL.

vars:extract
Наследие. Перенести свойства из .php в .data.json (не читается движком).

admin:passwd
Задать логин и пароль администратора (для входа в админку).
bash
php bin/cli.php admin:passwd --name=admin --password=secret
Записывает login и password_hash в config/admin.config.

user:new
Создать пользователя сайта (для страниц с "auth": true).
bash
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
Записывает хешированный пароль в БД.
⚠️ Это не администратор админки. Для входа в админку используйте admin:passwd.

trash:clear
Очистить карантин (папки *_trash_<timestamp>).
bash
php bin/cli.php trash:clear
По умолчанию (dry-run) — показывает список папок.
--force — удалить всё.
--older=7d — только старше 7 дней.

structure:check
Проверить целостность структуры.
bash
php bin/cli.php structure:check
Проверяет наличие всех 3 файлов для каждого элемента (Страницы, Блоки, Схемы), стыковку филлер↔слот. Выводит список ошибок или OK.

structure:repair
Умное восстановление структуры домена.
bash
php bin/cli.php structure:repair --domain=example.ru
Сканирует папки и:
- Восстанавливает папки алиасов (создает algoritm/ и algoritm.php).
- Создает отсутствующие .rules.json и .editor.json для блоков и схем.
⚠️ Не трогает страницы и настройки в site.config.

Специальный слой: bin/aicli.php (AI-фасад)
Это не команда CLI, а безопасный промежуточный слой для интеграции с LLM (Ollama).
bash
echo '{"command": "engine_status", "args": {}}' | php bin/aicli.php
Принимает JSON из STDIN. Имеет жесткий Whitelist команд (read_test_file, engine_status, filler_toggle). Защищает систему от несанкционированного доступа AI (запрет exec/system, валидация аргументов).

Общие флаги
--dry — предпросмотр без записи (vars:extract, trash:clear).
--force — без подтверждения (allias:remove, trash:clear).

Идемпотентность
db:migrate — не дублирует миграции.
*:new — возвращают ошибку, если объект уже существует.
structure:repair — безопасен, проверяет наличие перед созданием.

Чего в CLI нет (только UI)
CodeMirror-редактор (/admin/?p=editor).
Визуальный Drag&Drop редактор для Схем (/admin/?p=scheme:visual).
Профиль администратора и логи.
Управление банами.

Что делать при ошибке
1. structure:check — базовая диагностика.
2. structure:repair — умное восстановление структуры.
3. Проверить config/site.config и config/engine.config.

--- КОНЕЦ ДОКУМЕНТА ---