# Наблюдаемость: план и статус

Живой документ. Порядок: **P0 → P1 → P2 → P3**. Отмечено только то, что реально сделано.

## Статус

| Фаза | Что | Статус |
|---|---|---|
| **P0** | request_id, ошибки с контекстом, `health:check`, `alert:notify` | **готово** |
| **P1** | метрики запросов (1 строка/запрос), `metrics:snapshot`, `/metrics` по токену, ротация метрик | **готово** |
| **P2** | `cron:run` с flock и журналом, дедуп алертов, `report:daily` | **готово** |
| P3 | Loki/node_exporter/Sentry, страница в админке | по потребности |
| P3 | Loki/node_exporter/Sentry, страница в админке | по потребности |

## P0 — что сделано

### Единое событие (JSONL)

Все источники пишут одну форму:

```json
{"ts":"2026-11-18T00:20:00.123456Z","env":"prod","level":"error","channel":"app",
 "request_id":"df14f68997a6ede3","host":"damoony.ru","pid":1234,
 "message":"Uncaught exception: boom","context":{"class":"RuntimeException","trace":"..."}}
```

- `channel`: `app` (веб), `http` (middleware), `cli`, `db`;
- `request_id` — 16 hex, генерируется в точке входа;
- `host`/`pid` — для сопоставления с журналом веб-сервера.

### `request_id`

- `RequestId::initFromGlobals()` вызывается в `www/index.php`, `www/content.php`, `www/forms.php`;
- входящий `X-Request-Id` принимается **только** при совпадении `^[a-f0-9]{8,32}$`, иначе генерируется свой;
- ответ всегда содержит `X-Request-Id` (проверено: 301 и 200);
- `ErrorHandler` пишет id в лог и в тело 500-й страницы (`Request ID: ...`), в prod — без стека.

### `health:check`

```bash
php bin/cli.php health:check [--json]
```

Проверки: `config` (site.config валиден), `database` (SELECT 1), `storage` (`var/` на запись),
`disk` (свободно ≥ порога), `backup` (последний архив не старше N дней).
`status`: `ok` | `degraded` (есть warn) | `fail`; exit 1 только при `fail`.

### `alert:notify`

```bash
php bin/cli.php alert:notify --message="..." --level=critical --source=health
```

Каналы: файл (`alerts.file`, JSONL) и webhook (`alerts.webhook`, POST JSON, timeout 5s).
Если канал недоступен — warning; ошибка только если не сработал ни один.

### Конфиг

`config/observability.config` (gitignored, эталон — `.example`):

```json
{
  "health": { "backup_max_age_days": 2, "disk_min_free_mb": 1024 },
  "alerts": { "file": "var/alerts.log", "webhook": null }
}
```

Нет файла → дефолты. Битый JSON/типы → явная ошибка.

## P1 — сделано

Одна строка на запрос в `var/metrics/requests-YYYY-MM-DD.jsonl`:

```json
{"ts":"...","request_id":"...","host":"damoonyserver.ru","method":"GET","path":"/en/",
 "status":200,"duration_ms":2.4,"project":"damoony.ru","alias":"www",
 "db_queries":1,"db_ms":0.5}
```

- **нагрузка**: без записи на каждый запрос в БД и без локов; одна append-строка, без fsync;
  `metrics.sample=N` — писать каждый N-й запрос; `metrics.enabled=false` — выключить;
- **точки замера**: `FrontController` (project/alias), `AbstractProvider::query` (db_queries/db_ms);
- **`metrics:snapshot --since=1h|24h|7d [--format=prometheus]`** — агрегаты: статусы, хосты, алиасы,
  p50/p95/max, запросы к БД;
- **`/metrics.php?token=...`** — Prometheus text; пустой `metrics.token` = эндпоинт выключен (404);
- **ротация**: `metrics.keep_days` + `system:clean --force`.

Не сделано и осознанно отложено: `log:tail` (app-лог идёт в stderr — смотреть через journald/nginx),
обёртка всех PDO-вызовов (считаются только провайдеры).

## P2 — сделано

### `cron:run`

```bash
php bin/cli.php cron:run [--task=backup] [--dry-run]
```

- задачи и их включённость — `maintenance.config.cron.tasks` (`cleanup`, `content_gc`, `backup`, `structure`, `health`);
- `backup` выполняется по `cron.backup_domains` (пусто = все проекты);
- `flock` в `cron.lock_dir/cron.lock`: второй запуск пропускается с warning;
- журнал `var/log/cron-YYYY-MM-DD.jsonl` — по строке на задачу + итоговая `_cron`;
- при провале задачи — `alert:notify` (level=error, source=cron).

### Дедуп алертов

`alert:notify` помнит хэш `level|source|message` в `var/state/alerts.json` и подавляет повторы
в течение `alerts.dedup_minutes` (по умолчанию 60). `dedup_minutes: 0` — выключено.
Записи старше 7 дней чистятся автоматически.

### `report:daily`

```bash
php bin/cli.php report:daily [--since=24h] [--alert]
```

Сводка: запросы/5xx/p95, health, количество и возраст бэкапов, число алертов за период, диск.
`--alert` отправляет сводку через `alert:notify` (level=info, а при health=fail — error).

### Порог ошибок

`alerts.errors_5xx_per_5m` зарезервирован как порог для алертов по ошибкам; сейчас cron
алертит по факту провала задач (health/structure/backup), а не по счётчику — счётчик остаётся
за `report:daily` и ручным `metrics:snapshot`.

## P3 — по потребности

Loki/Promtail (JSONL уже совместим), textfile для node_exporter, Sentry через hook в `ErrorHandler`,
страница «Состояние» в админке.
