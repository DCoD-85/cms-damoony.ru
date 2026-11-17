# CLI: проекты, домены, алиасы

`--domain` принимает **ключ проекта** (`damoony.ru`) или **входной хост** (`damoony3.ru`).
Оба резолвятся в один проект; FS-пути строятся от `folder` (см. `config.md`).

## 1. Проект

| Команда | Что делает | Нюансы |
|---|---|---|
| `domain:list` | проекты из `domains_app` + наличие папки | показывает `folder`, `namespace`, мирроры, алиасы, `dir_exists` |
| `domain:new --domain=X [--folder=…] [--namespace=…] [--profile=clean\|minimal]` | создаёт папку, алиас `www`, глобальную схему, блок `center`, скин | профили: `clean` — только 3 файла страниц, `minimal` — плюс схема/блок/скин |
| `domain:remove --domain=X --force [--cascade]` | проект в карантин + очистка конфига | при >1 мирроре требуется `--cascade`; входящий миррор вместо ключа отклоняется с подсказкой |
| `domain:bind --domain=HOST --real=PROJECT` | добавить миррор | хост уникален; повторный вызов идемпотентен |
| `domain:unbind --domain=HOST` | снять миррор | основной хост (== ключ проекта) снять нельзя — только `domain:remove` |

После `domain:new` в конфиг пишутся: проект (`folder`, `namespace`), миррор `X`, алиас `www`, языки `ru,en`.

## 2. Алиас

| Команда | Что делает | Нюансы |
|---|---|---|
| `allias:list --domain=X` | алиасы проекта + наличие папки | миррор в `--domain` допустим |
| `allias:new --domain=X --name=Y [--profile=…]` | страницы (3 файла) + `lang/`, схема алиаса, скин | алиас обязан быть в конфиге или создаётся записью |
| `allias:remove --domain=X --name=Y --force` | папка алиаса + media в карантин, запись из конфига | состояние филлеров живёт в `.rules.json`, отдельно чистить не нужно |

## 3. Сборки

`new --domain=X --assembly=developer [--alias=www] [--namespace=…]`

- раскладывает `stubs/assemblies/{assembly}/` в `app/{folder}/`, `www/media/{folder}/{alias}/`, `www/media/shared/`;
- подставляет `{{domain}}`, `{{real}}`, `{{alias}}`, `{{namespace}}`, `{{timestamp}}`;
- если проект уже объявлен — переиспользует его `folder`/`namespace`;
- в конфиг добавляет проект, миррор, алиас и согласованные языки (`project.langs = alias.langs`).

Практика: сборка — единственный правильный способ поставить типовой каркас
(сайт, админка, магазин). Профили `clean/minimal/full` из старой версии заменены сборками.

## 4. Приоритет и перекрытия

- блоки: `.blockslocal/{name}/` перекрывает `.blocksglobal/{name}/`;
- схемы: `.schemeslocal/` перекрывает `.schemesglobal/`;
- скины: `www/media/{folder}/{alias}/skin/{name}/`.

Локальные сущности создаются `allias:new`/`filler:new`, глобальные — `block:new`/`scheme:new`.
