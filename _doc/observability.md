# Наблюдаемость: план и статус

Живой документ. Порядок: **P0 → P1 → P2 → P3**. Отмечено только то, что реально сделано.

## Статус

| Фаза | Что | Статус |
|---|---|---|
| **P0** | request_id, ошибки с контекстом, `health:check`, `alert:notify` | **готово** |
| P1 | метрики, `/metrics`, ротация app-логов, `log:tail` | не начато |
| P2 | `cron:run`, пороговые алерты с дедупом, `report:daily` | не начато |
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

## P1 — дальше

| # | Задача | Готово когда |
|---|---|---|
| 2.1 | Счётчики: `requests_total{status,host,alias}`, `request_duration_ms`, `errors_total`, `db_queries_total` | пишутся в `var/metrics/` |
| 2.2 | Точки замера: `Kernel` (статус+время), `Connection` (запросы) | нет «слепых» слоёв |
| 2.3 | `metrics:snapshot` / `/metrics` по токену | не публично |
| 1.3 | Ротация app-логов (`logs.app_keep_days`) + в `system:clean` | логи не растут |
| 1.4 | `log:tail --channel=app --lines=100` | без ручного доступа к файлам |

## P2 — дальше

`cron:run` с `flock` и журналом; `report:daily`; пороговые алерты (бэкап, диск, ошибки) с дедупом.

## P3 — по потребности

Loki/Promtail (JSONL уже совместим), textfile для node_exporter, Sentry через hook в `ErrorHandler`,
страница «Состояние» в админке.
