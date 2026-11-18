# Админка сервера

Админка — **обычный проект** `admin` в `domains_app` с зеркалом IP сервера.
Роль определяется сессией, а не URL.

## URL

```
https://<IP>/                 → 301 /{lang}/
https://<IP>/{lang}/          → нет сессии: форма входа
https://<IP>/{lang}/          → есть сессия: дашборд роли
https://<IP>/{lang}/logout    → выход
```

Локально: `http://127.0.0.1/ru/`.

## Устройство

```
app/.admin/www/algoritm/algoritm.php        → redirect /{lang}/
app/.admin/www/algoritm/lang/...            → login/dashboard
app/.admin/www/algoritm/lang/logout/...     → выход
app/.admin/.schemesglobal/admin.php         → схема
```

- проект: `admin`, folder `.admin`, alias `www`, langs `ru/en`;
- зеркало: IP сервера;
- роли: `superadmin`, `domainadmin` — только в сессии/БД;
- `superadmin` видит проекты, `domainadmin` — свой scope.

## Учётки

```
admin_accounts, admin_members, admin_sessions, admin_audit
```

```bash
php bin/cli.php admin:create --login=root --password='strong-pass' --role=superadmin
php bin/cli.php admin:create --login=docs --password='strong-pass' --role=domainadmin --scope=docs.damoony.ru
```

## Доступ

```json
"security": {
    "admin_ips": ["127.0.0.1", "203.0.113.10"]
}
```

- нет списка → loopback (fail-closed);
- Host должен быть IP (или `security.admin_hosts`);
- CSRF — отдельный cookie-токен; rate-limit 10/10 мин;
- cookie `ADMIN_SESSID`, 8 часов, отзыв при выходе/смене пароля;
- ответы `no-store`, `X-Frame-Options: DENY`.

## Прод

- nginx-vhost на IP → обычный `index.php` (без `admin.php`);
- `security.admin_ips` + nginx `allow/deny`;
- отдельный php-fpm pool — по `_dev/deploy/php-fpm/admin.conf`.
