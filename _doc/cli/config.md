# CLI: конфигурация `config/site.config`

Единственная схема — `domains_app`. Ключи `domains_real`, `domains_mirrors`,
`domains_mirrors_available`, `fillers` не существуют и не поддерживаются.
Старый файл — `config/site.config.old` (не читается).

## 1. Структура

```json
{
    "domains_app": {
        "damoony.ru": {
            "folder": "damoony.ru",
            "namespace": "damoony_ru",
            "scheme": "www",
            "stub": "maintenance",
            "default_alias": "www",
            "langs": ["ru", "en"],
            "aliases_storage": {
                "www": { "db": "", "prefix": "d1_www__" }
            },
            "mirrors": {
                "damoony.ru":  { "available": true },
                "damoony3.ru": { "available": true }
            }
        }
    },
    "alliases": {
        "damoony.ru": {
            "www":  { "available": true, "langs": ["ru", "en"], "stub": "maintenance" },
            "docs": { "available": true, "langs": ["ru"] }
        }
    }
}
```

## 2. Поля проекта (`domains_app.{key}`)

| Поле | Обяз. | Смысл | Валидация / умолчание |
|---|---|---|---|
| `key` | да | идентификатор проекта; он же ключ в `alliases` | строка |
| `folder` | да | папка в `app/` → `app/{folder}/` | без `..`, `/`, `\`; допускается ведущая точка (`.admin`) |
| `namespace` | да | PHP-namespace домена (`namespace damoony_ru\www;`) | `^[a-z][a-z0-9_]*$` |
| `scheme` | нет | схема проекта по умолчанию | `www` |
| `stub` | нет | заглушка для недоступного проекта | `maintenance` |
| `default_alias` | нет | алиас по умолчанию | `www` |
| `langs` | нет | языки проекта (надмножество языков алиасов) | `[]` |
| `aliases_storage` | нет | префикс таблиц алиаса | `prefix: ^[a-z0-9_]*$` |
| `mirrors` | нет | входные хосты проекта (host → `available`) | hostname или IPv4; хост уникален между проектами |

## 3. Поля алиаса (`alliases.{key}.{alias}`)

| Поле | Обяз. | Смысл | Валидация / умолчание |
|---|---|---|---|
| `alias` | да | имя алиаса → `app/{folder}/{alias}/` | `^[a-z][a-z0-9_]*$` |
| `available` | нет | включён ли алиас | `true` |
| `langs` | нет | языки алиаса | `[]`; должны быть подмножеством языков проекта, если у проекта они заданы |
| `stub` | нет | заглушка алиаса (переопределяет проектную) | — |

## 4. Инварианты (проверяются при загрузке)

1. `alliases.{key}` ссылается только на существующий проект в `domains_app`.
2. Один хост не может принадлежать двум проектам.
3. Языки алиаса ⊆ языки проекта (если `project.langs` не пуст).
4. `domains_app` может быть пустым (свежая установка), но ключ обязан существовать.

Нарушение любого инварианта — исключение при загрузке, а не «тихо проигнорировано».

## 5. Куда ведут пути

| Поле | Путь |
|---|---|
| `folder` | `app/{folder}/`, `www/media/{folder}/`, `content/{folder}/` |
| `namespace` | `namespace {namespace}\{alias};` в PHP-классах страниц |
| `mirrors[host]` | резолв входящего хоста → этот проект |
| `aliases_storage.prefix` | префикс физических таблиц алиаса (см. `data.md`) |

## 6. Миграция со старой схемы

`php bin/cli.php site:config:migrate` переносит `site.config.old` → новая схема:

| Старое | Новое |
|---|---|
| `domains_real[D]` (значение) | `domains_app[D].namespace` |
| ключ `D` (папка) | `domains_app[D].folder` |
| `domains_mirrors[H] == D` | `domains_app[D].mirrors[H]` |
| `domains_mirrors_available[H]` | `domains_app[D].mirrors[H].available` |
| `alliases[D]` | `alliases[D]` без изменений |
| `domains_scheme[D]`, `domains_stub[D]`, `domains_langs[D]`, `domains_aliases_storage[D]` | одноимённые поля проекта |
| `fillers` | не переносится: состояние филлеров живёт в `.blockslocal/{block}/{block}.rules.json` |

## 7. Практика

- `config/site.config` не коммитится (`.gitignore`); эталон схемы — `config/site.config.example`.
- Правки — только через команды (`domain:*`, `allias:*`, `lang:*`, `new`), не руками:
  они валидируют результат до записи.
