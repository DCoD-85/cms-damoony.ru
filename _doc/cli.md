--- НАЧАЛО ДОКУМЕНТА ---

CLI-команды
Точка входа: php bin/cli.php <command> [--key=value]. Все команды возвращают код возврата: 0 при успехе, 1 при ошибке. Результат — объект Result с полями ok и errors.

Философия CLI-First: CLI является единственным источником истины для файловых операций. Админка и AI-агенты — это лишь обертки над этими командами. 

Правило 3 файлов: Все сущности (Страницы, Блоки, Схемы) состоят из трех файлов: {name}.php, {name}.rules.json, {name}.editor.json.
Для страниц все три файла имеют единый префикс: algoritm_{name}.{php,rules.json,editor.json}. Root — algoritm/algoritm.{...}.

⚠️ Перед destructive-командами (allias:remove, trash:clear --force, block:remove, domain:remove) — коммит в git. Они перемещают/удаляют файлы.

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
Создаёт папку app/example.ru/, алиас www, .rules.json, глобальную схему www.php и блок center. Обновляет config/site.config.
⚠️ Порт и trailing-dot нормализуются (stage-3) — 192.168.1.10:8080 сработает.

domain:remove
Удалить домен (в карантин).
bash
php bin/cli.php domain:remove --domain=example.ru --force
Без --force команда отменяется. С --force папка домена переименовывается в <name>_trash_<timestamp>/, а конфиг site.config полностью очищается от упоминаний домена. Физическое удаление — trash:clear.

allias:new
Создать дополнительный алиас.
bash
php bin/cli.php allias:new --domain=example.ru --name=admin
Создаёт app/example.ru/admin/algoritm/ (с algoritm.php и algoritm.rules.json), обновляет site.config.
⚠️ Корневая страница аллиаса (/) создается автоматически этой командой. Не нужно создавать её через page:new.
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
Создаёт три файла в .alliasglobal/top/: top.php (код класса), top.rules.json (тип и статус), top.editor.json (пустой).

block:remove
Удалить блок.
bash
php bin/cli.php block:remove --domain=example.ru --name=top
Удаляет папку .alliasglobal/top/ целиком.
⚠️ Команда удаляет файлы сразу, без карантина. Коммит в git перед запуском — обязателен. (Защита от Path Traversal встроена).

filler:new
Создать filler (переопределение блока для алиаса).
bash
php bin/cli.php filler:new --domain=example.ru --allias=www --block=top
Создаёт в папке блока: top_www.php (класс), top_www.rules.json (настройки), top_www.editor.json (данные UI). Обновляет site.config (fillers = true).

filler:toggle
Включить/выключить filler.
bash
php bin/cli.php filler:toggle --domain=example.ru --allias=www --block=top --value=0
--value=0 — выключить, иначе — включить. Обновляет site.config.

page:new
Создать страницу.
bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
--name — имя страницы (только латиница, цифры, подчеркивания, без дефисов).
--parent — путь родительской страницы (по умолчанию — корень).
Создаёт:
- about/algoritm_about.php — PHP-класс (extends Page). Пустой stub генерируется через PageCompiler.
- about/algoritm_about.rules.json — полные дефолты: {"available":[],"auth":false,"search":true,"default":false}.
- about/algoritm_about.editor.json — пустой ([]).
- Добавляет 'about' в available родителя (root: algoritm/algoritm.rules.json, вложенный: {parent}/algoritm_{parent}.rules.json).
⚠️ Родительская страница должна существовать. Если нет — ошибка «Родительская папка не найдена».
⚠️ Страница не рендерит схему по умолчанию (MANIFEST.md §6.1). Вызов SchemeRenderer нужно добавить вручную (guide.md §4) или использовать page:write --with_schema=1.

page:write
Записать контент (JSON) в существующую страницу. Главный инструмент для AI и автоматизации.
bash
php bin/cli.php page:write --domain=example.ru --allias=www --name=about --json='[{"type":"h1","content":"Привет"}]'
--name — имя страницы. Используйте __root__ для записи на главную страницу аллиаса (/).
--json — JSON-массив блоков (поддерживаются типы: h1, h2, h3, text, img, link, button, ul; атрибуты: class, id, style, alt).
--json_b64 — base64-строка JSON (используется для безопасной передачи из aicli.php).
--with_schema=1 — оборачивает контент в глобальную схему (подтягивает шапку/подвал).
Генерирует безопасный PHP-код через PageCompiler (без сырого PHP).
⚠️ --parent несовместим с --name='__root__' (главная страница всегда в корне).

page:read
Прочитать PHP-код существующей страницы.
bash
php bin/cli.php page:read --domain=example.ru --allias=www --name=about
Полезно для AI-агента, чтобы «увидеть» текущее состояние страницы перед редактированием. Возвращает сгенерированный PHP-код через Context::step().
Спец. имя: --name='__root__' читает главную страницу аллиаса.

page:toggle
Переключить флаг страницы.
bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --name=about --key=auth --value=1
--name — имя страницы. Используйте __root__ для главной.
--parent — путь родительской страницы (опционально).
--key — auth, search или default.
--value — 0 или 1.
Обновляет algoritm_{name}.rules.json указанной страницы (с полным набором полей available/auth/search/default).
⚠️ Аргумент --path из старой версии не поддерживается. Используйте --name (для главной — __root__).

scheme:new
Создать схему (каркас страницы).
bash
php bin/cli.php scheme:new --domain=example.ru --name=default
Создаёт три файла в .alliasscheme/: default.php (рендер), default.rules.json (настройки), default.editor.json (правила для Drag&Drop UI). Сканирует .alliasglobal для заполнения доступных блоков.

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
Проверить целостность структуры (severity-aware).
bash
php bin/cli.php structure:check
Проверяет:
- наличие всех 3 файлов для каждой страницы (algoritm_{name}.{php,rules.json,editor.json});
- наличие algoritm/algoritm.rules.json (root);
- отсутствие устаревших {dir}/.rules.json;
- стыковку филлер↔слот;
- наличие схем и их .rules.json;
- наличие .php/.rules.json/.editor.json для блоков.

Severity:
- `✗` (error) — отсутствует .php, отсутствует root algoritm.rules.json, отсутствует схема.
- `⚠` (warning) — отсутствует .editor.json, отсутствует .rules.json у блока/схемы, устаревший .rules.json, филлер без слота.

Exit code: 0 при отсутствии errors (warnings допускаются), 1 при наличии errors.

structure:repair
Умное восстановление структуры домена.
bash
php bin/cli.php structure:repair --domain=example.ru
Сканирует папки и: восстанавливает папки алиасов (создает algoritm/ и algoritm.php), создает отсутствующие .rules.json и .editor.json для блоков и схем.
⚠️ Не трогает страницы и настройки в site.config.

migrate:page-rules
Одноразовая миграция старых .rules.json на новую модель.
bash
php bin/cli.php migrate:page-rules [--domain=example.ru] [--dry]
Идемпотентна. Шаги:
1. root: algoritm/.rules.json → algoritm/algoritm.rules.json (с union available).
2. подпапки: {dir}/.rules.json → {dir}/algoritm_{name}.rules.json (с union available).
3. нормализация полей (available/auth/search/default) для всех algoritm_*.rules.json.
4. создание пустых .editor.json для существующих .php.
--dry — предпросмотр без записи.
--domain — только указанный домен (по умолчанию все).

Специальный слой: bin/aicli.php (AI-фасад)
Это не команда CLI, а безопасный промежуточный слой для интеграции с локальными LLM (Ollama).
bash
echo '{"command": "engine_status", "args": {}}' | php bin/aicli.php
Принимает JSON из STDIN. Имеет жесткий Whitelist команд, защищает систему от несанкционированного доступа AI (запрет exec/system, строгая валидация аргументов).

Текущий whitelist (что агент может вызывать через aicli):
- domain:new (только домены из config/agent.config)
- allias:new (только для разрешенных доменов)
- page:new (только для разрешенных доменов)
- page:write (только для разрешенных доменов; пустое name конвертируется в __root__)
- page:read (только для разрешенных доменов)
- filler_toggle
- engine_status
- read_test_file

Валидация аргументов:
- domain — должен быть в $allowedDomains (из config/agent.config).
- allias, name — regex /^[a-z][a-z0-9_]*$/.
- Блоки для page:write — должен быть JSON-массив.
- Пустое name в page:write → __root__ (главная страница).
- Опасные команды (user:new, db:migrate) — отклоняются.

Общие флаги
--dry — предпросмотр без записи (vars:extract, trash:clear, migrate:page-rules).
--force — без подтверждения (allias:remove, domain:remove, trash:clear).

Идемпотентность
db:migrate — не дублирует миграции.
*:new — возвращают ошибку, если объект уже существует.
structure:repair — безопасен, проверяет наличие перед созданием.
migrate:page-rules — идемпотентна (повторный запуск 0/0/0).

Чего в CLI нет (только UI)
CodeMirror-редактор (/admin/?p=editor).
Визуальный Drag&Drop редактор для Схем (/admin/?p=scheme:visual).
Профиль администратора и логи.
Управление банами.
Копирование/удаление схем (scheme:copy/scheme:remove) — реализованы в SchemeController.

Всего команд: 22.

Что делать при ошибке
1. structure:check — базовая диагностика (severity-aware).
2. structure:repair — умное восстановление структуры.
3. migrate:page-rules — если есть устаревшие .rules.json (warning в structure:check).
4. Проверить config/site.config и config/engine.config.

--- КОНЕЦ ДОКУМЕНТА ---