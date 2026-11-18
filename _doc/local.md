# Локальная среда (Windows, localserver)

## Пути

| Что | Путь |
|---|---|
| Актуальный проект | `E:\localserver\domains\damoonyserver.ru` |
| Клиентский тест | `E:\localserver\domains\damoonyclient.ru` |
| Nginx | `E:\localserver\tools\nginx\conf\nginx.conf` |
| PHP CLI | `E:\localserver\tools\php8.5.12\php.exe` |
| PHP-FPM/CGI | `127.0.0.1:9502` |

Старый `damoony3.ru` удалён; его домен больше не используется.

## Домены

- `damoonyserver.ru` — основной локальный сайт (проект `damoony.ru`);
- `persona.damoonyserver.ru`, `developer.damoonyserver.ru`, `admin.damoonyserver.ru` — алиасы;
- `damoonyclient.ru` — отдельный клиентский сайт (своя БД).

Acrylic DNS резолвит `*.damoonyserver.ru` и `*.damoonyclient.ru` в `127.0.0.1`.
Nginx: catch-all + отдельные server-блоки для этих доменов.

## БД

- основной сайт: `damoony_local_db`;
- клиент: `damoonyclient_db`;
- серверная админка: таблицы `admin_*` в основной БД.

## Запуск

```powershell
E:\localserver\start.bat
```

Или вручную:

```powershell
Start-Process E:\localserver\tools\php8.5.12\php-cgi.exe -ArgumentList '-b','127.0.0.1:9502','-c','E:\localserver\tools\php8.5.12\php.ini'
Start-Process E:\localserver\tools\mariadb\bin\mariadbd.exe -ArgumentList '--datadir=E:\localserver\tools\mariadb\data'
Start-Process E:\localserver\tools\nginx\nginx.exe -ArgumentList '-p','E:\localserver\tools\nginx','-c','E:\localserver\tools\nginx\conf\nginx.conf'
```

## Админка

```
http://127.0.0.1/admin.php
```

Учётки создаются CLI:

```bash
php bin/cli.php admin:create --login=root --password='strong-pass-1' --role=superadmin
```

## Проверки

```bash
php bin/cli.php structure:check
php bin/cli.php health:check
php bin/cli.php lang:check
vendor/bin/phpunit -c _dev/phpunit.xml
```
