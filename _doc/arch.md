# Архитектура

## Компоненты

```
www/index.php          front controller
src/Core/App           FrontController, AppFactory, PageHandler
src/Core/Domain        SiteDomainResolver
src/Core/Routing       FileRouter
src/UI                 Page, Scheme, Region, Builder, Element
src/Cli                bin/cli.php
app/{folder}/          файлы сайтов
config/                engine.config, site.config
var/                   логи, кэш, бэкапы, сессии
```

## Домен → проект → алиас

`config/site.config`:

```json
{
  "domains_app": {
    "example.ru": {
      "folder": "example.ru",
      "namespace": "example_ru",
      "mirrors": { "example.ru": { "available": true } },
      "langs": ["ru", "en"],
      "aliases": {
        "www":  { "available": true, "langs": ["ru", "en"] },
        "docs": { "available": true, "langs": ["ru"] }
      },
      "default_alias": "www",
      "scheme": "www"
    }
  }
}
```

- `mirrors` — входные хосты; один хост принадлежит только одному проекту;
- `aliases` — поддомены/разделы проекта; `www` — основной;
- `folder` — папка в `app/`; `namespace` — PHP-namespace;
- `langs` проекта; языки алиаса — подмножество.

Запрос:

```
Host → SiteDomainResolver → project + alias
URI  → FileRouter → PageHandler → Page
```

Язык — первый сегмент URL: `/{lang}/...`. Если его нет, используется язык по умолчанию.

## Файловая структура сайта

```
app/example.ru/
  .schemesglobal/        схемы проекта
  .blocksglobal/         глобальные блоки
  .i18nglobal/           переводы
  .dbglobal/schema.json  схема таблиц проекта
  .dbprovider/           провайдеры домена
  www/
    .schemeslocal/       локальные схемы алиаса
    .blockslocal/        локальные блоки
    .dbprovider/         провайдеры алиаса
    algoritm/
      algoritm.php
      algoritm.rules.json
      algoritm.editor.json
      lang/
        algoritm_lang.php
        ...
```

## Правило 3 файлов

Для страницы, блока, схемы, филлера:

| Файл | Назначение |
|---|---|
| `*.php` | логика/класс |
| `*.rules.json` | доступность, auth, csrf, search |
| `*.editor.json` | метаданные редактора |

Структура проверяется `structure:check`, восстанавливается `structure:repair`.

## Страницы

Класс страницы наследует `Damoony\UI\Page\Page` и реализует:

```php
protected function content(): Node|Response|null
```

- возвращает `Node` — контент;
- возвращает `Response` — редирект/404/JSON;
- исключения обрабатываются error handler.

Имя класса строится из пути: `app/{folder}/{alias}/algoritm/lang/about/algoritm_lang_about.php` → класс `algoritm_lang_about` в namespace `{namespace}\{alias}`.

## Схемы и блоки

Схема — массив с `tag`, `class`, `children` (слоты) и `meta` (css/js).  
Блок — класс `RegionInterface`:

```php
name(): string
enabled(): bool
render(RegionContext $context): Node
```

- глобальные блоки: `.blocksglobal`;
- локальные: `.blockslocal` (перекрывают глобальные);
- схема выбирается проектом/алиасом; локальная схема перекрывает глобальную.

## UI

- `Element` / `Attrs` — DOM-узлы;
- `Builder` — хелперы (`div`, `a`, `ul`, `button`, ...);
- `Text` — экранированный текст;
- `Page` — контент и guard;
- `Document` — сборка HTML.

## AJAX

- клиент: `www/media/shared/scripts/cliajax.js`;
- сервер: при `X-Requested-With: XMLHttpRequest` `PageHandler` отдаёт JSON:

```json
{"ok": true, "blocks": {...}, "layout": "...", "form": {...}}
```

- обновляемые блоки — `Page::ajaxBlocks()`;
- без JS формы работают обычным POST.

## Модули

`ModuleRegistry` и контракты `ModuleInterface`, `PageModuleInterface`, `RequestGuardInterface` — для подключаемых модулей.  
Текущая админка модулем **не является**: это проект `admin` (см. `_doc/admin.md`).

## CLI

`bin/cli.php` — единая точка операций: миграции, домены, страницы, схемы, бэкапы, админы.  
Подробности — `_doc/cli.md`.
