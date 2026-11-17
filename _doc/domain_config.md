# Конфигурация доменов (`config/site.config`)

Единый источник истины для роутинга, FS-путей и PHP namespace проектов движка.

Формат введён в v3.2. **Обратной совместимости нет** — старая трёхмассивная схема
(`domains_real` / `domains_mirrors` / `domains_mirrors_available`) удалена.

## Структура

```json
{
    "domains_app": {
        "damoony.ru": {
            "folder": "damoony.ru",
            "namespace": "damoony_ru",
            "mirrors": {
                "damoony.ru": { "available": true },
                "damoony3.ru": { "available": true }
            }
        },
        "admin": {
            "folder": "admin",
            "namespace": "admin",
            "mirrors": {
                "187.188.0.1": { "available": true }
            }
        }
    }
}
```

## Семантика

| Ключ | Назначение |
|---|---|
| `domains_app` | Реестр проектов. Ключ — `project_key`, произвольный идентификатор (домен, имя, IP) |
| `domains_app[key].folder` | Папка проекта в `/app/`.唯一 аргумент для `Paths::domain()`, `algoritm()`, `media()`, `content()` |
| `domains_app[key].namespace` | PHP namespace классов проекта (страницы, блоки, схемы) |
| `domains_app[key].mirrors` | Зеркала проекта. Ключ — входящий хост |
| `domains_app[key].mirrors[host].available` | Доступность зеркала. `false` → отдаётся заглушка |

`folder` и `namespace` — разные сущности и никогда не подменяют друг друга.
`project_key` может совпадать с `folder`, но не обязан.

## Цепочка резолва

1. При загрузке конфига строится **обратный индекс** `host -> { project_key, available }`.
2. `index[H]` → `project_key` (нет записи → контекст 404).
3. `available === false` → заглушка (`domains_stub`).
4. `domains_app[project_key].folder` → `Paths::domain(folder)` → `/app/{folder}/`.
5. `domains_app[project_key].namespace` → PHP-классы проекта.

Индекс строится один раз при загрузке: резолв на каждый запрос — O(1),
независимо от числа проектов и зеркал (заявленный масштаб — до 100000 хостов).

## Инварианты

- `folder` уникален в пределах `domains_app`.
- `namespace` уникален в пределах `domains_app`.
- Входящий хост встречается в `mirrors` ровно один раз суммарно по всем проектам.
- У каждого зеркала есть `available` — гарантировано вложенностью, а не дисциплиной.
- У каждого проекта есть хотя бы одно зеркало.

## Примеры

Один проект, один хост:

```json
{
    "domains_app": {
        "example.ru": {
            "folder": "example.ru",
            "namespace": "example_ru",
            "mirrors": { "example.ru": { "available": true } }
        }
    }
}
```

Один проект, несколько зеркал:

```json
{
    "domains_app": {
        "example.ru": {
            "folder": "example.ru",
            "namespace": "example_ru",
            "mirrors": {
                "example.ru": { "available": true },
                "example.com": { "available": true },
                "www.example.ru": { "available": false }
            }
        }
    }
}
```

Проект с не-доменным ключом:

```json
{
    "domains_app": {
        "admin": {
            "folder": "admin",
            "namespace": "admin",
            "mirrors": { "187.188.0.1": { "available": true } }
        }
    }
}
```

## Целевой контракт резолвера

Ещё **не реализовано**. Ориентир для `src/Core/Domain/ConfigDomainResolver.php`:

```php
// индекс строится в конструкторе
private array $hostIndex = [];   // host => ['project_key' => string, 'available' => bool]

public function projectKey(string $host): ?string;
public function isAvailable(string $host): bool;
public function folder(string $projectKey): string;
public function namespace(string $projectKey): string;
```

CLI-команды получают доступ через трейт `ResolvesRealDomain`, который делегирует
в резолвер и возвращает **folder** (не namespace, не project_key).

## Что не меняется

Ключи, не относящиеся к реестру проектов, остаются как есть:

- `alliases[folder][allias]` — разделы, `available`, `langs`
- `alliases_real` — маппинг имени раздела
- `domains_stub[folder].current`, `alliases_stub[folder][allias].current` — заглушки
- `domains_scheme[folder]` — схема по умолчанию

Все они ключуются **folder**, как и раньше.

## Соответствие старому формату

| Было | Стало |
|---|---|
| `domains_real[folder] = namespace` | `domains_app[key].folder` + `domains_app[key].namespace` |
| `domains_mirrors[host] = folder` | `domains_app[key].mirrors[host]` |
| `domains_mirrors_available[host] = bool` | `domains_app[key].mirrors[host].available` |

Причина замены: в старой схеме значение `domains_real` было namespace, но в части
фикстур выглядело как хост (`'real.ru' => 'real.ru'`), из-за чего резолверы путали
folder и namespace. Плюс три массива требовали ручной синхронизации.

## Открытый вопрос

`DomainContext` сейчас несёт поле `real` (= folder). Нужно решить при миграции:
оставить `real` как folder и добавить `projectKey`, либо переименовать `real` → `folder`.
Решение принимается на шаге правки `DomainContext.php`.

## Команды управления (план)

- `config:init-domains` — создаёт минимальный валидный `site.config`.
- `domain:list` — таблица проектов, папок, namespace и зеркал.
- `domain:bind` / `domain:unbind` — управление зеркалами внутри проекта.
