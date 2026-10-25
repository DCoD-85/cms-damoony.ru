# Сценарии проекта Damoony

Источник: docs/philosophy.md, docs/contracts.md, _devdoc/_auditfinal/.
20 исполняемых сценариев. Каждый сценарий — вход, шаги, ожидаемый результат.
Сценарии используются на Этапе 2 для проверки каркаса нового движка.
Golden files хранятся в tests/fixtures/scenarios/NN/expected/.

Формат сценария:
- ID
- Название
- Вход
- Шаги
- Ожидание
- Источник в _auditfinal

---

## S01 Создание домена
Вход: domain=example.ru, namespace=example
Шаги: CLI domain:new --name=example.ru --namespace=example
Ожидание: структура app/<logic>/domain/example.ru/ создана, config обновлён, dry-run не пишет на диск
Источник: _auditfinal/11, _auditfinal/28

## S02 Создание алиаса
Вход: domain=example.ru, allias=www
Шаги: CLI allias:new --domain=example.ru --name=www
Ожидание: структура allias/www/ создана, filler-файлы созданы для всех блоков, config обновлён
Источник: _auditfinal/11, _auditfinal/26

## S03 Удаление алиаса с карантином
Вход: domain=example.ru, allias=www
Шаги: CLI allias:remove --domain=example.ru --name=www --force
Ожидание: папка перемещена в trash, config очищен; при ошибке config — папка восстановлена
Источник: _auditfinal/11, _auditfinal/26

## S04 Создание блока
Вход: domain=example.ru, block=custom
Шаги: CLI block:new --domain=example.ru --name=custom
Ожидание: папка блока создана, манифест blocks.json обновлён, filler-заглушки созданы
Источник: _auditfinal/12, _auditfinal/27

## S05 Удаление блока
Вход: domain=example.ru, block=custom
Шаги: CLI block:remove --domain=example.ru --name=custom
Ожидание: папка удалена, манифест обновлён, fillers очищены
Источник: _auditfinal/12, _auditfinal/27

## S06 Создание filler
Вход: domain=example.ru, allias=www, block=top
Шаги: CLI filler:new --domain=example.ru --allias=www --block=top
Ожидание: файлы top_www.php и top_www.data.php созданы, config обновлён
Источник: _auditfinal/12, _auditfinal/29

## S07 Toggle filler
Вход: domain=example.ru, allias=www, block=top, value=0
Шаги: CLI filler:toggle --domain=example.ru --allias=www --block=top --value=0
Ожидание: fillers[example.ru][www][top]=false
Источник: _auditfinal/12, _auditfinal/29

## S08 Создание страницы
Вход: domain=example.ru, allias=www, parent=index, name=about
Шаги: CLI page:new --domain=example.ru --allias=www --parent=index --name=about
Ожидание: algoritm_index_about.php и .data.json созданы, available в родителе обновлён
Источник: _auditfinal/13, _auditfinal/31

## S09 Toggle страницы
Вход: domain=example.ru, allias=www, path=about, key=auth, value=1
Шаги: CLI page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
Ожидание: about.data.json: default.auth=true
Источник: _auditfinal/13, _auditfinal/31

## S10 Создание схемы
Вход: domain=example.ru, scheme=visuo
Шаги: CLI scheme:new --domain=example.ru --name=visuo
Ожидание: visuo.data.php создан, манифест schemes.json обновлён
Источник: _auditfinal/35

## S11 Рендер страницы (index)
Вход: GET /www/example.ru/
Шаги: DomainResolver -> Router -> Region -> Scheme -> UiNode
Ожидание: HTML документ, doctype, head с meta, body с регионами top/left/center/right/bottom, policy, debug
Источник: _auditfinal/25, _auditfinal/34, _auditfinal/35

## S12 Рендер страницы (404)
Вход: GET /www/example.ru/unknown
Шаги: Router -> not found
Ожидание: HTTP 404, страница ошибки через Region center, error_head 404
Источник: _auditfinal/08, _auditfinal/30

## S13 Maintenance mode
Вход: GET /www/example.ru/, stub=maintenance
Шаги: DomainResolver -> available=false
Ожидание: HTML заглушки, HTTP 503 или 200 в зависимости от конфига, текст технические работы
Источник: _auditfinal/05, _auditfinal/10

## S14 AJAX-ответ (min)
Вход: GET /www/example.ru/login с X-Requested-With
Шаги: Router -> login -> ajax_guest -> visuostruct_min
Ожидание: JSON с полями error, messages, release, form, referer
Источник: _auditfinal/26, _auditfinal/31

## S15 AJAX-ответ (ajax)
Вход: GET /www/example.ru/ с X-Requested-With
Шаги: Router -> index -> ajax
Ожидание: JSON с полями error, address, layers (header, top, left, center, right, bottom)
Источник: _auditfinal/26, _auditfinal/31

## S16 Мультиязычность
Вход: GET /www/example.ru/ru-ru/
Шаги: DomainResolver -> language=ru-ru -> Region head -> meta
Ожидание: HTML с lang=ru-ru, ссылки переключения языков
Источник: _auditfinal/34, _auditfinal/80

## S17 Смена языка через cookie
Вход: GET /?lang=en-en
Шаги: Middleware -> cookie::set(lang, en-en) -> redirect
Ожидание: 307 redirect на URL с en-en, cookie установлена
Источник: _auditfinal/34, _auditfinal/80

## S18 Смена скина
Вход: GET /?skin=dark
Шаги: Middleware -> cookie::set(skin, dark) -> redirect
Ожидание: 307 redirect, cookie установлена, следующий запрос использует dark
Источник: _auditfinal/34, _auditfinal/80

## S19 Миграция БД
Вход: пустая БД, миграции 001..007
Шаги: CLI db:migrate
Ожидание: таблицы news, files, file_downloads, login_attempts, users.timezone/lang, seed данные
Источник: _auditfinal/31, _auditfinal/78

## S20 Экспорт правил из PHP в .data.json
Вход: domain=example.ru, allias=www
Шаги: CLI vars:extract --domain=example.ru --allias=www
Ожидание: .data.json созданы, public-свойства из .php удалены (или dry-run показывает план)
Источник: _auditfinal/14, _auditfinal/33

---

## Golden files

Каждый сценарий имеет tests/fixtures/scenarios/NN/:
- input/ — входные данные (конфиги, файлы, запросы)
- expected/ — ожидаемый вывод (HTML, JSON, файловая структура)
- diff — функция сравнения для HTML (нормализация пробелов, порядка атрибутов)

## Гейт Этапа 2

Каркас нового движка считается готовым к Этапу 3, если:
- все 20 сценариев выполняются
- golden files совпадают
- PHPStan 8 без ошибок
- покрытие src/Core и src/Cli не ниже 80%

## Что НЕ входит в сценарии

- админка (отдельные сценарии на Этапе 3)
- безопасность (проверяется отдельным чек-листом)
- производительность (отдельный benchmark)