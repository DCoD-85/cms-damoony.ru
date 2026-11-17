# CLI: AIUI-сессии

Сессия — изолированная копия проекта, в которой можно безопасно генерировать и править
файлы до применения в production.

## 1. Раскладка

| Что | Путь |
|---|---|
| Копия проекта | `app/.sess_{tab}.{folder}/` |
| Копия media | `www/media/.sess_{tab}.{folder}/` |
| Метаданные | `app/.sess_{tab}.{folder}/.session.json` |

`tab` — 8–32 hex. Метаданные: `tab_id`, `project`, `folder`, `created_at`, `production_hash`,
`admin_session_id`, `permissions`.

## 2. Команды

| Команда | Что делает | Нюансы |
|---|---|---|
| `session:start --tab=T [--admin-sess=…]` | копирует проект и media, считает `production_hash` | папка проекта обязана существовать; повторный старт с тем же `tab` — ошибка |
| `session:apply --tab=T --admin-sess=…` | сессия → production | обязателен `--admin-sess`; сверяет `production_hash`; при расхождении — «Конфликт: production изменился» |
| `session:discard --tab=T` | сессия и её media в карантин | идемпотентна |
| `session:list` | активные сессии, возраст, `ttl_remaining` (24 ч) | читает `.session.json` |

`apply` переносит production в `app/_trash_sess_*` (резерв), затем сессию — в production,
удаляет `.session.json`. Media сессии **не** применяется — уводится в `www/media/.trash/`,
чтобы не было «новый домен + старый скин».

## 3. Права

`.session.json → permissions` — явные флаги, по умолчанию:

```json
{ "page:write": true, "block:write": true, "filler:write": true,
  "skin:edit": false, "scheme:save": false, "structure:check": true, "net:fetch": true }
```

Проверка — `SessionGuard`: пустой `--session` = обычный CLI-режим, непустой — сессия обязана
существовать, а флаг должен быть строго `true`, иначе отказ «Операция запрещена в этой сессии».

## 4. Какие команды учитывают `--session`

| Команда | Что делает `--session` |
|---|---|
| `page:new`, `page:write` | пишут в `app/.sess_{tab}.{folder}/`, проверяют существование сессии |
| `block:write`, `filler:write` | то же + permission-флаг |
| `scheme:save`, `skin:edit` | проверяют permission-флаг (пишут в production-каталоги) |

Остальные команды `--session` не принимают и работают с production. Это осознанное ограничение:
изоляция внедрена там, где AI генерирует контент.

## 5. Ограничения

- `production_hash` считается по всем `.editor.json` проекта; ручные правки production
  после старта сессии приведут к конфликту при `apply` — это защита, а не баг.
- Откат `apply` — только через карантин (`app/_trash_sess_*`) и `git`.
- На Windows (php-cgi) после `page:new --session` может понадобиться рестарт PHP-CGI (opcache).
