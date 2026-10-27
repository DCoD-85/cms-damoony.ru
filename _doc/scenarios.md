--- НАЧАЛО ФАЙЛА ---

# Сценарии проекта Damoony

Документ содержит перечень ключевых сценариев (Use Cases), которые проверяют работоспособность и стабильность архитектуры. 
Эти сценарии покрыты автоматическими тестами в директориях `_dev/tests/Unit/` и `_dev/tests/Scenarios/`.

## 1. Сценарии управления структурой (CLI и Файловая система)

### S01 Создание домена
Вход: `php bin/cli.php domain:new --domain=example.ru --namespace=example`
Ожидание: Создана папка `app/example.ru/`, обновлен `config/site.config` (добавлены `domains_real`, `domains_namespace`).

### S02 Создание алиаса
Вход: `php bin/cli.php allias:new --domain=example.ru --name=www`
Ожидание: Создана папка `app/example.ru/www/algoritm/`, обновлен `config/site.config` (добавлен `alliases_real`).

### S03 Создание глобального блока
Вход: `php bin/cli.php block:new --domain=example.ru --name=top`
Ожидание: Созданы файлы `top.php` и `top.data.php` в `app/example.ru/.alliasglobal/top/`.

### S04 Создание филлера (переопределение блока)
Вход: `php bin/cli.php filler:new --domain=example.ru --allias=www --block=top`
Ожидание: Созданы файлы `top_www.php` и `top_www.data.php`, в конфиг добавлен `fillers` (включен по умолчанию).

### S05 Создание страницы
Вход: `php bin/cli.php page:new --domain=example.ru --allias=www --name=about`
Ожидание: Создана папка `about` с файлом `algoritm_about.php` и `.rules.json` в `app/example.ru/www/algoritm/`.

### S06 Создание глобальной схемы
Вход: `php bin/cli.php scheme:new --domain=example.ru --name=default`
Ожидание: Создан файл `default.data.php` в `app/example.ru/.alliasscheme/`.

## 2. Сценарии рендеринга и роутинга (Frontend)

### S07 Успешный рендеринг главной страницы
Вход: `GET http://example.ru/`
Шаги: `DomainResolver` -> `FileRouter` -> `PageHandler` -> `SchemeRenderer`.
Ожидание: HTTP 200, HTML документ, собранный из слотов схемы (header, main, footer).

### S08 Рендеринг 404 (Страница не найдена)
Вход: `GET http://example.ru/unknown-path`
Шаги: `FileRouter` не находит правило -> `FrontController` возвращает `ErrorPage::notFound()`.
Ожидание: HTTP 404, HTML страница ошибки.

### S09 Режим обслуживания (Maintenance)
Вход: `GET http://example.ru/` при `"domains_available": false`.
Ожидание: HTTP 503, HTML страница-заглушка (текст «Технические работы»).

### S10 Проверка статуса алиаса
Вход: `GET http://doc.example.ru/` при `"alliases_available": {"example.ru": {"doc": false}}`.
Ожидание: HTTP 503 (заглушка), при этом основной сайт `www` продолжает работать.

### S11 Доступ к админке при выключенном сайте
Вход: `GET http://example.ru/admin/` при `"domains_available": false`.
Ожидание: Админка открывается (игнорирует заглушку 503), позволяя включить сайт обратно.

## 3. Сценарии безопасности и авторизации

### S12 Защита страницы авторизацией
Вход: `GET http://example.ru/about`, где в `.rules.json` указано `"auth": true`.
Шаги: Гость перенаправляется на `/login`.
Ожидание: HTTP 302 (Redirect на `/login?return=/about`).

### S13 Успешный вход (Login)
Вход: `POST http://example.ru/login` с корректным CSRF-токеном, логином и паролем.
Шаги: `DbAuth` проверяет хеш в таблице `users` -> `SessionAuth` создает сессию.
Ожидание: HTTP 302 (Redirect на исходную страницу), пользователь авторизован.

### S14 Защита от CSRF
Вход: `POST http://example.ru/login` без CSRF-токена.
Ожидание: HTTP 403 (CSRF token invalid).

### S15 Управление привязками доменов
Вход: В админке в разделе «Привязанные» добавить `example.online`.
Шаги: Изменение `config/site.config` -> `domains_real["example.online"] = "example.ru"`.
Ожидание: `example.online` начинает открывать тот же сайт, что и `example.ru`.

## 4. Сценарии вывода (AJAX)

### S16 Ответ в формате JSON (Min)
Вход: `GET http://example.ru/api-endpoint` с заголовком `X-Requested-With: XMLHttpRequest`.
Шаги: Контроллер возвращает объект `MinStruct`.
Ожидание: HTTP 200, `Content-Type: application/json`, структура с полями `error`, `messages`, `release`, `form`.

### S17 Ответ в формате JSON (Ajax Layout)
Вход: Аналогично S16.
Шаги: Контроллер возвращает объект `AjaxStruct`.
Ожидание: JSON с полями `error`, `address`, `layers` (слои `dt_top`, `dt_center` и т.д.).

---

## Проверка покрытия
Все перечисленные сценарии покрыты автоматическими тестами (`PHPUnit`) и проходят статический анализ (`PHPStan level 8`).
Запуск тестов: `$PHP vendor/bin/phpunit -c _dev/phpunit.xml`

--- КОНЕЦ ФАЙЛА ---

Следующий файл на переписывание — **`_doc/audit-map.md`**. Скидывай его оригинал. (Или, если считаешь, что карта аудита старого проекта больше не нужна в документации нового движка, можем её удалить).