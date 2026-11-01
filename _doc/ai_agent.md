--- НАЧАЛО ДОКУМЕНТА ---

Инструкция для AI-Агента (Универсальная)
Ты — AI-агент, работающий с Damoony Engine. Этот документ объединяет правила для архитектора (инфраструктура), разработчика (создание контента) и интегратора (безопасный фасад aicli.php).

Перед началом прочитай MANIFEST.md. Все P0 и P1 закрыты. Остались только задачи из P2 (техдолг).

Правило 3 файлов: Любая сущность (Страница, Блок, Схема) состоит из трех файлов: `{name}.php` (код), `{name}.rules.json` (настройки), `{name}.editor.json` (данные UI). 
Для страниц все три файла имеют единый префикс: `algoritm_{name}.{php,rules.json,editor.json}` в одной папке. Root алиаса — `algoritm/algoritm.{php,rules.json,editor.json}`. Глобальные манифесты (`blocks.json`, `schemes.json`) удалены.

Единственная точка входа — `bin/aicli.php`. AINET (`net:*`) и CLI (`media:*`, `page:*`, ...) не вызываются напрямую. AICLI сам роутит:
- `page:*`, `domain:*`, `allias:*`, `scheme:*`, `block:*`, `filler:*` → `bin/cli.php` (shell_exec)
- `net:status`, `image:search`, `fetch` → `src/Ainet/AinetFacade` (in-process)
- `image:download` → `AinetFacade` (bytes) → `bin/cli.php media:store` (shell_exec, FS)

---

ЧАСТЬ 1: АРХИТЕКТУРА И ИНФРАСТРУКТУРА (System)
Твоя задача: создавать домены, алиасы, схемы и управлять структурой. Ты не пишешь ядро (src/).

1.1. Главные правила
- **Только `bin/aicli.php`.** Любая операция (создание страниц, поиск картинок, скачивание) — через aicli (JSON через STDIN).
- **Коммит в git** перед destructive-операциями (`domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `trash:clear --force`, `migrate:page-rules`, `media:remove`). AI не имеет доступа к ним — но владелец может их делать вручную.
- **Визуальные редакторы (Drag&Drop)** остались ТОЛЬКО для Схем. Страницы и Блоки редактируются через JSON (через AI) или CodeMirror (вручную).
- **Не пиши сырой PHP.** Ты передаёшь JSON-массив блоков через `page:write`, движок компилирует в PHP через PageCompiler (безопасно, без RCE).
- **AINET — библиотека, не фасад.** Ты не знаешь про `bin/ainet.php`. Ты работаешь только через `bin/aicli.php` (`net:*` команды).

1.2. Структура файлов
- Домен: папка `app/<realDomain>/`. realDomain резолвится из `site.config` (`domains_real[<host>]`).
- Алиасы (www, doc) — подпапки внутри домена.
- Инфраструктурные папки скрыты точкой: `.alliasglobal` (блоки), `.alliasscheme` (схемы).
- Страницы алиаса: `app/<domain>/<allias>/algoritm/<path>/algoritm_<path>.{php,rules.json,editor.json}`.
- Root алиаса: `app/<domain>/<allias>/algoritm/algoritm.{php,rules.json,editor.json}`.
- Медиа: `www/media/<domain>/<allias>/<category>/<hash>.<ext>` + `<hash>.meta.json`.

1.3. Справочник команд (только через aicli)
Домены и алиасы:
- `domain:new --domain=example.ru` — создать домен (создает www и схему, root через PageCompiler).
- `allias:new --domain=example.ru --name=doc` — создать алиас (root-страница через PageCompiler).

Блоки, схемы, филлеры (не в твоём whitelist, но для понимания):
- `block:new`, `block:remove`, `filler:new`, `filler:toggle`, `scheme:new`, `scheme:copy`, `scheme:remove`, `scheme:save` — управляются владельцем.

Страницы:
- `page:new --domain=example.ru --allias=doc --name=about [--parent=docs]` — создать страницу (3 файла, родитель должен существовать).
- `page:write --domain=example.ru --allias=doc --name=about --json='[...]'` — **ГЛАВНАЯ КОМАНДА ДЛЯ AI**. Записывает контент.
  - Используй `__root__` в качестве имени (`--name='__root__'`), чтобы записать контент на главную страницу аллиаса (/).
  - `--parent` несовместим с `__root__`.
  - `with_schema=1` — оборачивает в глобальную схему (шапка/подвал).
- `page:read --domain=example.ru --allias=doc --name=about` — прочитать PHP-код страницы. Для главной — `__root__`.
- `page:toggle --domain=example.ru --allias=doc --name=about --key=auth --value=1` — переключить флаг (auth/search/default). `--path` не поддерживается.

Net (AINET, через aicli):
- `net:status` — диагностика (провайдеры, whitelist, кэш, rate limit).
- `image:search --query="coffee" --count=5 [--provider=wikimedia]` — поиск картинок. Возвращает `{results: [{url, preview_url, width, height, license, author, source, title}]}`.
- `image:download --url="..." --domain=example.ru --allias=www --category=coffee --alt="Coffee cup"` — скачать картинку и сохранить в `www/media/`. Возвращает `{local_url, hash, path, size, mime, width, height, cached}`.
- `fetch --url="https://ru.wikipedia.org/api/..." --type=json|text` — GET к whitelisted URL (Wikipedia, GitHub API).

Диагностика (владелец, не AI):
- `structure:check`, `structure:repair`, `migrate:page-rules`, `trash:clear`, `media:store/remove/list` — не в твоём whitelist.

Разрушительные операции (недоступны AI): `domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `media:remove`, `trash:clear`, `migrate:page-rules`, `admin:passwd`, `user:new`, `db:migrate`.

Path traversal guard: все команды с `--domain`/`--name` проверяют `..` и regex. `net:*` дополнительно проверяет URL (SSRF, whitelist).

---

ЧАСТЬ 2: ГЕНЕРАЦИЯ КОНТЕНТА (Logic)
⚠️ **КРИТИЧЕСКОЕ ПРАВИЛО:** Ты (AI) **НЕ ПИШЕШЬ PHP-КОД** для страниц. Ты генерируешь **JSON-массив блоков**, который движок сам безопасно компилирует в PHP через `Builder`.

2.1. Формат JSON для `page:write`
Ты должен передавать массив объектов. Поддерживаемые типы:
- `h1`, `h2`, `h3` (заголовки)
- `text` (параграф)
- `img` (картинка — используй `local_url` из `image:download`!)
- `link` (ссылка)
- `button` (кнопка)
- `ul` (список)

Поддерживаемые атрибуты (опционально):
- `class` (CSS-классы)
- `id` (HTML id)
- `style` (inline-стили)
- `url` (для link/button)
- `alt` (для img)

**Пример идеального JSON для `page:write`:**

```json
[
  {"type": "h1", "content": "Добро пожаловать", "style": "color:#2c3e50; text-align:center;"},
  {"type": "text", "content": "Наш сайт посвящен кофе."},
  {"type": "img", "content": "/media/example.ru/www/coffee/a3f5c1e9.jpg", "style": "width:100%; max-width:600px;", "alt": "Coffee cup"},
  {"type": "ul", "items": ["Пункт 1", "Пункт 2"]},
  {"type": "link", "url": "/about", "content": "О нас", "style": "color:blue;"}
]
```

⚠️ **Для `img` всегда используй `local_url` из `image:download`**, а не внешний URL. Внешние URL могут отвалиться, а `local_url` — стабильный (`/media/<domain>/<allias>/<category>/<hash>.<ext>`).

2.2. Шаблоны страниц (для понимания)
Если бы ты писал PHP-код (что делать не нужно), он выглядел бы так:

```php
final class algoritm_about extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');
        $content->append(Builder::h(1)->content('О нас'));
        return $content;
    }
}
```

Но ты просто передаешь `[{"type":"h1","content":"О нас"}]` в `page:write`.

2.3. Шаблон региона (.alliasglobal)
Блоки (шапки/подвалы) все еще реализуются как PHP-классы (если они не создаются через AI). Они реализуют `RegionInterface`.

```php
final class alliasglobal_top implements RegionInterface
{
    public function name(): string { return 'top'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('top');
        $wrap->append(Builder::h(2)->content('Логотип'));
        return $wrap;
    }
}
```

---

ЧАСТЬ 3: AI-ФАСАД И ИНТЕГРАЦИЯ (Agent API)
Твоя задача: работать через безопасный интерфейс `bin/aicli.php`, который связывает локальную LLM (Ollama, например Qwen 2.5 Coder) с ядром.

3.1. Архитектура (Agentic Loop)
Ты не имеешь прямого доступа к терминалу или файловой системе. Ты работаешь через безопасный цикл:

```
[AI-Модель (Ollama, Qwen)]
      ↕ (JSON-план)
[Python Скрипт (Agentic Loop)]
      ↕ (JSON через STDIN)
[bin/aicli.php (Единственный AI-фасад)]
      │
      ├── app/* команды (page:*, domain:*, allias:*, ...)
      │       └── shell_exec → bin/cli.php → FS (app/)
      │
      ├── media:* команды (только для aicli image:download → media:store)
      │       └── shell_exec → bin/cli.php → FS (www/media/)
      │
      └── net:* команды (net:status, image:search, image:download, fetch)
              ├── fetch, image:search, net:status → src/Ainet/* (in-process) → Интернет
              └── image:download → src/Ainet/HttpClient (bytes в память)
                                    └── shell_exec → bin/cli.php media:store → FS (www/media/)
```

- **Agentic Loop:** Скрипт отправляет промпт в Ollama, получает JSON-план, передает его в `aicli.php`, получает результат.
- **Разделение прав:** Ты не можешь выполнить `system('rm -rf /')` или `curl evil.com`. `aicli.php` принимает только разрешенные команды (Whitelist).
- **SSRF-защита:** Все `net:*` URL проходят UrlGuard (whitelist доменов, private IP block, scheme/port check, ручные редиректы).
- **AINET не пишет на диск.** `image:download` получает bytes в память, отдаёт в CLI `media:store`, тот пишет. Единственный источник истины для FS — CLI.

3.2. Whitelist (Разрешенные команды)
Список находится в `bin/aicli.php` (массив `$allowedCommands`).

**Разрешены (12):**
- app/: `read_test_file`, `engine_status`, `filler_toggle`, `domain:new`, `allias:new`, `page:new`, `page:write`, `page:read`
- net/: `net:status`, `image:search`, `image:download`, `fetch`

**Запрещены (вызов вернёт `{"ok": false, "error": "Command '<name>' is not allowed.", "hint": "Allowed: ..."}`):**
- `user:new`, `db:migrate`, `admin:passwd`
- `domain:remove`, `allias:remove`, `block:new`, `block:remove`, `filler:new`
- `scheme:new`, `scheme:copy`, `scheme:remove`, `scheme:save`
- `trash:clear`, `migrate:page-rules`, `structure:check`, `structure:repair`
- `media:store`, `media:remove`, `media:list`

3.3. Безопасность AI-слоя
- **Валидация аргументов:** Все аргументы проверяются через строгие Regex. Если ты передашь `../../etc/passwd` — скрипт вернет ошибку.
- **Whitelist доменов (agent.config):** `domain` для `page:write`/`page:new`/`page:read`/`allias:new`/`filler_toggle`/`image:download` должен быть в `config/agent.config` (`allowed_domains`). Иначе — `Security violation: Domain 'X' is not in the allowed list.`
- **Whitelist URL (ainet.config):** `url` для `image:download`/`fetch` должен быть в `config/ainet.config` (`allowed_domains`). Иначе — `Domain 'X' not in whitelist`.
- **Жесткий вызов:** app/* и media:* команды вызываются через `shell_exec` только с валидированными аргументами (escapeshellarg + regex).
- **JSON компиляция:** Твой JSON проходит через `PageCompiler`, который использует только безопасные методы `Builder` и `var_export` для строк.

3.4. Важные нюансы для LLM (Qwen 7B)
- **Без висячих запятых:** Валидатор Python падает на запятых перед `]` и `}`. Будь предельно внимателен при генерации JSON.
- **Без дефисов:** Имена страниц и алиасов должны содержать только `[a-z0-9_]`. Никаких дефисов (`how-to-adopt` запрещено, используй `how_to_adopt`).
- **Главная страница:** Не создавай `page:new` с именем `home` или `/`. Главная страница создается автоматически при `allias:new`. Для записи контента используй `page:write` с именем `__root__`.
- **`__root__` только для главной:** Если `--parent` передан вместе с `--name='__root__'` — ошибка.
- **Не пиши в чужой домен:** `page:write` проверяет `domain` по `allowed_domains` из `agent.config`.
- **Имена блоков/алиасов:** `[a-z][a-z0-9_]*` (начинается с буквы, не с цифры).
- **Картинки — только через `image:download`:** не вставляй внешние URL в `img`. Сначала `image:search` → выбери `url` → `image:download` → получи `local_url` → вставь в `page:write` как `{"type":"img","content":"<local_url>"}`.
- **Категория для картинок:** `[a-z0-9_-]{1,32}`, по теме страницы (coffee, tea, animals, hero, logo).
- **Alt-текст:** Всегда передавай `alt` в `image:download` — он пишется в `.meta.json` и в `<img alt="...">`.

---

ЧАСТЬ 4: СЦЕНАРИЙ «САЙТ С КАРТИНКАМИ» (рекомендуемый)
Пошаговый план для 10-страничного сайта с картинками:

**Шаг 1:** `domain:new --domain=example.ru` (если домена нет)
**Шаг 2:** `allias:new --domain=example.ru --name=shelter` (если алиаса нет)
**Шаг 3:** `net:status` (проверить, что AINET настроен)
**Шаг 4 (для каждой страницы):**
- `image:search --query="<тема>" --count=3` → выбери 1-2 лучших (width/height ≥ 800, license=CC/PD)
- `image:download --url="<url>" --domain=example.ru --allias=shelter --category=<тема> --alt="<описание>"` → получи `local_url`
- `page:write --domain=example.ru --allias=shelter --name=<page> --json='[{"type":"h1",...},{"type":"img","content":"<local_url>","alt":"..."}]'`

**Шаг 5:** `structure:check` (проверить, что warnings нет) — но это делает владелец, не AI.

Пример полного JSON-плана (для Python-цикла, 3 команды):
```json
[
  {"command": "image:search", "args": {"query": "coffee cup", "count": 3}},
  {"command": "image:download", "args": {"url": "<url_из_шага_1>", "domain": "example.ru", "allias": "coffee", "category": "coffee", "alt": "Coffee cup on table"}},
  {"command": "page:write", "args": {"domain": "example.ru", "allias": "coffee", "name": "about", "blocks": [{"type": "h1", "content": "О нас"}, {"type": "img", "content": "<local_url_из_шага_2>", "alt": "Coffee cup"}]}}
]
```

---

ЧАСТЬ 5: МИГРАЦИЯ PAGE-RULES
Если на диске остались старые `.rules.json` (root `algoritm/.rules.json` или `{dir}/.rules.json`), `structure:check` покажет warning:
`⚠ Устаревший /X/.rules.json — запустите migrate:page-rules`

Действия:
1. Предпросмотр: `php bin/cli.php migrate:page-rules --dry`.
2. Боевой прогон: `php bin/cli.php migrate:page-rules`.
3. Повторный `structure:check` — warning должен исчезнуть.

Шаги миграции (для понимания):
1. root: `algoritm/.rules.json` → `algoritm/algoritm.rules.json` (с union `available`).
2. подпапки: `{dir}/.rules.json` → `{dir}/algoritm_{name}.rules.json` (с union `available`).
3. нормализация полей (`available`, `auth`, `search`, `default`) для всех `algoritm_*.rules.json`.
4. создание пустых `.editor.json` для существующих `.php`.

Миграция идемпотентна — повторный запуск даёт `0/0/0`.
⚠️ AI не имеет доступа к `migrate:page-rules`. Если видишь warning — сообщи владельцу.

---

ЧАСТЬ 6: ДИАГНОСТИКА И ОЧИСТКА (только владелец, не AI)
- `structure:check` — severity-aware. `✗` (error) — критично: отсутствует .php, отсутствует root algoritm.rules.json, отсутствует схема. `⚠` (warning) — некритично. Exit 0 при отсутствии errors.
- `structure:repair --domain=X` — восстанавливает отсутствующие root-страницы (через PageCompiler), rules.json/editor.json для блоков и схем. Path traversal guard.
- `trash:clear` — очистка карантина (5 уровней: app/, app/<domain>/, .alliasscheme/, .alliasglobal/, www/media/.trash/). Флаги: `--force`, `--older` (7d/24h/30m), `--domain` (только один домен). Dry-run по умолчанию. Symlink-safe Fs::rmrf.
- `media:list --domain=X` — список медиа (для отладки).
- `media:remove --domain=X --allias=Y --category=Z --hash=H --force` — в карантин.

---

ЧЕК-ЛИСТ ПЕРЕД СДАЧЕЙ
1. Ты сгенерировал JSON-массив, а не PHP-код.
2. В JSON нет висячих запятых.
3. Имена страниц содержат только `_` (подчеркивания), без дефисов.
4. Для записи на главную страницу использован `__root__`, без `--parent`.
5. Для инфраструктуры (создание доменов/страниц) использован CLI через `aicli.php`.
6. Domain — из `config/agent.config` (иначе будет `Security violation`).
7. Для каждой картинки: `image:search` → `image:download` → `local_url` → `page:write` (не внешний URL в `img`).
8. `image:download` содержит `alt` (описание картинки).
9. Если видишь warning про устаревший `.rules.json` — сообщи владельцу (AI не запускает `migrate:page-rules`).
10. Для удаления структур (если разрешено владельцем) — карантин + `--force`, не `unlink`.

ССЫЛКИ
MANIFEST.md — канон (✅/🟡/❌, P0/P1/P2).
cli.md — полный справочник команд (27 команд).
ainet.md — дизайн AINET (SSRF, провайдеры, конфиг).
architecture.md — жизненный цикл (включая AI-слой).
contracts.md — контракты и ограничения.
guide.md — руководство разработчика (включая §15: path traversal и карантин).

--- КОНЕЦ ДОКУМЕНТА ---