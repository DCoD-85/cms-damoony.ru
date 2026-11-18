# Админка сервера

## Вход

```
https://<IP>/                 → admin.php → /app/admin/auth/
/app/admin/auth/              → логин (без сессии)
/app/admin/superadmin/        → роль superadmin
/app/admin/domainadmin/       → роль domainadmin
/app/admin/auth/logout/       → выход
```

- отдельный entrypoint `www/admin.php`, публичный `index.php` не участвует;
- Host обязан быть IP сервера или в `security.admin_hosts`;
- роль берётся из серверной сессии, **не из URL**;
- `domainadmin` на `/superadmin/` → 404;
- без сессии → 302 на `/app/admin/auth/`.

## Учётки

Отдельные таблицы, не persona/users:

```
admin_accounts, admin_members, admin_sessions, admin_audit
```

Создание первого админа:

```bash
php bin/cli.php admin:create --login=root --password='strong-pass' --role=superadmin
php bin/cli.php admin:create --login=docs --password='strong-pass' --role=domainadmin --scope=docs.damoony.ru
```

Пароль — минимум 10 символов, хранится `password_hash`.
Сессия — 8 часов, HttpOnly cookie `ADMIN_SESSID`, отзыв при смене пароля.
Аудит: `login.ok`, `login.failed`, `logout`, `password.changed`.

## Доступ

```json
"security": {
    "admin_ips": ["127.0.0.1", "203.0.113.10"],
    "admin_hosts": ["admin.example.com"]
}
```

- нет списка → только loopback (fail-closed);
- проверка Host + `REMOTE_ADDR`;
- CSRF у формы входа — отдельный токен;
- rate-limit: 10 попыток / 10 минут на IP;
- ответы: `Cache-Control: no-store`, `X-Frame-Options: DENY`, `nosniff`.

## Страницы

- `app/.admin/superadmin/` — проекты, домены, алиасы, языки;
- `app/.admin/domainadmin/` — домен, алиасы;
- `app/.admin/auth/` — вход/выход;
- схема — `app/.admin/.schemesglobal/admin.php`.

## Прод-изоляция

- `_dev/deploy/nginx/prod-admin-ip.conf` — vhost на IP;
- `_dev/deploy/php-fpm/admin.conf` — отдельный pool/пользователь;
- CLI остаётся вне web (SSH/root), веб не выполняет shell.

## Дальше

- 2FA;
- отдельная auth DB и DB-пользователь;
- управление конфигами/бэкапами/cron из UI;
- приложение/API.
