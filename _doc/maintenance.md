# `config/maintenance.config` — настройки обслуживания

Необязательный файл (gitignored). CLI читает его как **дефолты**: флаг команды всегда важнее.
Файла нет → работают встроенные значения из таблицы ниже. Некорректный JSON/тип → ошибка при
запуске команды (тихо не игнорируется).

Эталон — `config/maintenance.config.example`.

## Схема

```json
{
  "backups": { "keep": 7, "parts": ["app", "content", "media", "db"], "compress_media": false },
  "trash": { "ttl_days": 0 },
  "sessions": { "ttl_hours": 24 },
  "content": { "gc_ttl_days": 30, "max_mb": 25, "min_side_px": 200 },
  "logs": { "cli_keep_days": 14 },
  "ratelimit": { "storage_keep_days": 7 }
}
```

| Ключ | По умолч. | Где применяется |
|---|---|---|
| `backups.keep` | `7` | `backup:create` без `--keep`: сколько архивов проекта хранить |
| `backups.parts` | все | `backup:create` без `--only`: состав архива |
| `backups.compress_media` | `false` | сжимать ли `media/` (по умолчанию store — картинки не сжимаются) |
| `trash.ttl_days` | `0` | `trash:clear` без `--older`: `0` — чистить всё, `>0` — только старше N дней |
| `sessions.ttl_hours` | `24` | `session:list` → `ttl_remaining` |
| `content.gc_ttl_days` | `30` | `content:gc` без `--older` |
| `content.max_mb` | `25` | `content:store` — лимит размера файла |
| `content.min_side_px` | `200` | `content:store` — минимальная сторона изображения |
| `logs.cli_keep_days` | `14` | `system:clean --force` — ротация `var/log/cli/*.log` |
| `ratelimit.storage_keep_days` | `7` | `system:clean --force` — чистка `var/ratelimit`, `var/form-ratelimit` |

Правила:

- `0` у `ttl_days`/`*_keep_days` = «не ограничивать» (для `backups.keep` — не удалять старые);
- значения — целые `≥ 0`; `parts` — подмножество `app, content, media, db`;
- неизвестные ключи игнорируются (обратная совместимость).

## Примеры

```bash
# Хранить 3 последних бэкапа и не сжимать media
php bin/cli.php backup:create --domain=damoony.ru          # возьмёт keep=3 из конфига

# Разовая чистка старше 3 дней, игнорируя конфиг
php bin/cli.php trash:clear --force --older=3d

# Уборка логов и rate-limit по конфигу
php bin/cli.php system:clean --force
```

## Чего здесь нет (намеренно)

- `ainet.cache_ttl_days` — живёт в `ainet.config`;
- `db.*` — в `engine.config`;
- хосты, языки, алиасы — в `site.config` (`domains_app`);
- расписания и `cron:run` — не реализованы; крон настраивается снаружи, команды готовы к вызову.
