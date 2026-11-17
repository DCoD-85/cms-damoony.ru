# CLI: страницы, блоки, филлеры, схемы, скины, языки, контент

Сквозное правило: **3 файла** — `[name].php` + `[name].rules.json` + `[name].editor.json`.
Исключений нет; `structure:check` (фаза 2 движка) проверяет это же.

## 1. Страницы

Файлы: `app/{folder}/{alias}/algoritm/…`

| Команда | Что делает | Нюансы |
|---|---|---|
| `page:list` | рекурсивный список страниц | строки contain имена переходов из `available` (не regex), флаги `auth/search/default` |
| `page:new --name=N [--parent=P]` | 3 файла страницы | `parent` должен существовать; в правила родителя добавляется переход `N` |
| `page:write --name=N (--json \| --json_file \| --json_b64) [--with_schema=1]` | JSON-блоки → PHP через `PageCompiler` | источника три: приоритет `--json`, затем `--json_file`, затем `--json_b64` |
| `page:read --name=N` | отдать PHP-код | — |
| `page:toggle --name=N --key=auth\|search\|default --value=1\|0` | флаг в `algoritm_{name}.rules.json` | `__root__` — корневая страница алиаса |

**AI не пишет PHP.** `page:write` принимает JSON-блоки; компилятор фильтрует теги
(`Text::raw` оставляет только `<a>`, `<strong>`), URL проходит `UrlSanitizer`.
Тип `img`: URL передаётся в поле `content` (не `url`).

`page:write` синхронизирует `page_refs` в `/content/**/<hash>.meta.json`:
добавляет упоминание новой ссылки и убирает устаревшую.

## 2. Блоки и филлеры

| Что | Команды | Где |
|---|---|---|
| Глобальный блок | `block:new/write/edit/remove/list` | `app/{folder}/.blocksglobal/{name}/` |
| Локальный (филлер) | `filler:new/write/edit/toggle/list` | `app/{folder}/{alias}/.blockslocal/{name}/` |

- `block:write` / `filler:write` — JSON → `BlockCompiler`;
- `block:edit` / `filler:edit` — правка одного файла (`code|rules|editor`) через CodeMirror;
  лимиты 64 KB (rules) / 1 MB; `code` требует `allow_php_blocks=true` и `--allow_php`;
- `filler:toggle` меняет **только** `enabled` в `.rules.json` — конфиг `site.config` не участвует;
- `filler:new` требует существующий глобальный блок (филлер — это переопределение).

## 3. Схемы

| Команда | Что делает |
|---|---|
| `scheme:new --name=N` | 3 файла схемы + список доступных блоков в `editor.json` |
| `scheme:save --name=N --scheme_json=… \| --scheme_json_b64=…` | визуальное дерево → `editor.json` + компиляция PHP |
| `scheme:copy --name=N` | копия (`N_copy`, `N_copy_1`, …) |
| `scheme:remove --name=N --force` | 3 файла в карантин |
| `scheme:list` | схемы проекта, отметка схемы по умолчанию |

Guard'ы `scheme:save`: whitelist тегов (нет `script/iframe/style/form/input`), битые слоты
отбрасываются, `--session` + permission `scheme:save` для AIUI-сессий.

## 4. Скины

`www/media/{folder}/{alias}/skin/{name}/` — `css/*.css`, `js/main.js`, `img/`, `skin.json`.

- `skin:new` создаёт скелет;
- `skin:edit --file=css/<name>.css|js/main.js` — whitelist файлов, лимит 1 MB,
  блокировка `@import` и `url(javascript:…)`; недостающие `}` дописываются, лишние — ошибка;
- `skin:remove --force` — в `www/media/.trash/`;
- `skin:list` — список скинов алиаса.

Skin подключается **из схемы** (`meta.css`/`meta.js`) токенами `{skin}`, `{real}`, `{alias}`.

## 5. Языки

Две области: языки проекта (`domains_app[key].langs`) и алиаса (`alliases[key][alias].langs`).

| Команда | Что делает |
|---|---|
| `lang:list [--allias=A] [--with-config=1]` | файлы `.i18nglobal` / `.i18nlocal` + проекция конфига |
| `lang:add --lang=xx [--allias=A]` | создаёт `{xx}.json`, обновляет конфиг |
| `lang:remove --lang=xx --force [--allias=A]` | файл в `var/trash/`, запись из конфига |
| `lang:repair [--fix=1] [--prune=1]` | drift файлов ↔ конфига; без флагов — отчёт |
| `lang:sync --allias=A` | языки алиаса = языки проекта + создать файлы |

Инварианты: язык алиаса обязан быть объявлен у проекта; первый `lang:add` на уровне проекта
подтягивает языки, уже объявленные на алиасах; удаление языка проекта, который используется
алиасом, отклоняется с перечислением алиасов. `lang:repair --promote` не существует.

## 6. Контент

`/content/{folder}/{alias}/{category}/{hash}.{ext}` + `{hash}.meta.json` — **вне webroot**,
отдаётся через `www/content.php`.

| Команда | Что делает |
|---|---|
| `content:store (--data_b64 \| --data_file) --mime=M [--category=C] [--alt=…]` | запись байтов с валидацией |
| `content:list [--category=C]` | список с `mime`, `size`, `page_refs` |
| `content:remove --category=C --hash=H --force` | файлы в `content/.trash/` |
| `content:gc --force \| --dry [--older=7d]` | orphan-контент в карантин |

Валидация `content:store`: MIME из белого списка (`jpeg/png/gif/webp/svg+xml`), ≤ 25 MB,
≥ 200×200 (кроме SVG), дедупликация по sha256, проект должен быть в
`config/agent.config.allowed_domains`.

Ключевое отличие от старых версий: контент ключуется **папкой проекта**, а не входным хостом —
мирроры не плодят копии.
