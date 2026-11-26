# Админка

Серверная админка — отдельное приложение, доступное по IP. Роль берётся из сессии, URL её не содержит.

## URL

```
http://<IP>/                 → 301 /{lang}/
http://<IP>/{lang}/          → нет сессии: форма входа
http://<IP>/{lang}/          → есть сессия: дашборд роли
http://<IP>/{lang}/logout    → выход
```

Локально: `http://127.0.0.1/ru/`.

## Разделы

| URL | Кто | Что |
|---|---|---|
| `/ru/` | все роли | дашборд |
| `/ru/content/` | superadmin, domainadmin | страницы, JSON/PHP, медиа |
| `/ru/blocks/` | superadmin, domainadmin | глобальные/локальные блоки, JSON/PHP |
| `/ru/projects/` | superadmin | проекты, зеркала, алиасы |
| `/ru/accounts/` | superadmin | админы, роли, сброс пароля |
| `/ru/ops/` | superadmin | БД, диск, структура, бэкап, cron |
| `/ru/audit/` | superadmin | журнал действий, `?page=N` |
| `/ru/domain/` | domainadmin | домен: схемы, блоки, БД, алиасы |

## Роли и права

`AdminPolicy`: **capability + scope**, роль — набор прав.

| Роль | Права | Scope |
|---|---|---|
| superadmin | все | server |
| domainadmin | domain/alias/content/audit | свой домен |

Проверки — по правам, не по URL. `domainadmin` не видит серверные разделы (404) и чужие домены.

## Учётки

Таблицы: `admin_accounts`, `admin_members`, `admin_sessions`, `admin_audit`.

```bash
php bin/cli.php admin:create --login=root --password='strong-pass-1' --role=superadmin
php bin/cli.php admin:create --login=docs --password='strong-pass-2' --role=domainadmin --scope=example.ru
php bin/cli.php admin:password --login=root --password='new-strong-pass'
```

- пароль ≥ 10 символов; логин 3–64 (`a-z0-9_.-`);
- `domainadmin` требует `--scope`;
- сброс пароля отзывает все сессии;
- в `admin_audit` пишутся: вход/выход и ошибки входа, создание админа, смена пароля, переключение проектов/алиасов/зеркал, действия с контентом, медиа и блоками;
- аудит: `/ru/audit/?page=N`, по 50 записей.

## Устройство

```
app/.admin/www/algoritm/algoritm.php        → redirect /{lang}/
app/.admin/www/algoritm/lang/...            → login/dashboard
app/.admin/www/.blockslocal/top|left/...    → топ-бар и меню
app/.admin/.schemesglobal/admin.php         → схема
app/.admin/www/.blockslocal                 → локальные блоки
```

Проект `admin` в `domains_app` — техническая запись (folder `.admin`, alias `www`, mirror IP). В списке проектов он скрыт.

## Доступ

```json
"security": {
    "admin_ips": ["127.0.0.1", "203.0.113.10"]
}
```

- пустой `admin_ips` → только loopback (fail-closed);
- CSRF-cookie, rate-limit 10/10 мин;
- cookie `ADMIN_SESSID`, 8 часов;
- `Cache-Control: no-store`;
- `Content-Security-Policy: ... frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`;
- на сервере: `site.config` — `root:www-data 660`, иначе админка не сможет переключать алиасы.

## Ограничения текущей версии

- 2FA не реализована;
- отдельная auth DB/пользователь БД — не выделены;
- AI-вход отложен;
- вынос в `apps/` — следующий этап.
