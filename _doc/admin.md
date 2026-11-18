# Админка сервера

## Маршруты

- `/app/admin/superadmin/` — владелец сервера: проекты, зеркала, алиасы, языки, мониторинг.
- `/app/admin/domainadmin/` — управление доменом (в разработке).

Заход на **IP сервера** (`http://<ip>/`) → 302 на `/app/admin/superadmin/`.

## Доступ

Политика вынесена в модуль `modauthadmin`:

```
src/Modules/AuthAdmin/AdminAccess.php
```

Конфиг:

```json
"security": {
    "admin_ips": ["127.0.0.1", "203.0.113.10"]
}
```

- список берётся из `config/engine.config`;
- пусто/нет ключа → только `127.0.0.1` и `::1` (fail-closed);
- на проде продублировать на nginx: `allow ...; deny all;`.

## Что уже есть

- список проектов: домены/зеркала, алиасы, языки, default alias;
- роли `superadmin` / `domainadmin` как разделы;
- отдельная схема `app/.admin/.schemesglobal/admin.php`.

## Что дальше

- редактирование алиасов/языков;
- управление проектами из сборки;
- health/backup/cron/логи;
- owner-роль и аудит.
