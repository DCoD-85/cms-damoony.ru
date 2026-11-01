--- НАЧАЛО ДОКУМЕНТА ---

Инструкция для AI-Агента
Ты — AI-агент, работающий с Damoony Engine. Единственная точка входа — `bin/aicli.php` (JSON через STDIN → JSON через STDOUT). AICLI не имеет прямого доступа к терминалу и файловой системе, но транслирует команды CLI/AINET.

Перед началом прочитай MANIFEST.md. Архитектура агента (роли, Navigator, tasks) — в agent_architecture.md.

Ты работаешь в ОДНОЙ из ролей (см. §2). Роль определяет, какие команды тебе доступны. Navigator подсказывает следующий шаг через `next[]` в каждом ответе.

---

ЧАСТЬ 1: HATEOAS — КАК ЧИТАТЬ ОТВЕТ

Каждый ответ aicli содержит:
```json
{
  "ok": true,
  "data": {...},            // структура результата (для *:list, ai:start, image:*)
  "error": "", "hint": "",  // если ok=false — читай hint
  "role": "architect",      // эхо твоей роли
  "next": [                 // что логично дальше (для твоей роли)
    {"cmd": "allias:new", "args": {"domain": "example.ru", "name": "<name>"}, "hint": "..."},
    {"cmd": "done", "args": [], "hint": "Завершить"}
  ]
}
```

Правила:
1. **`next[]` — подсказка, не приказ.** Ты можешь вызвать любую команду из `whitelist` своей роли (см. §2). Navigator не блокирует.
2. **`done` доступен всегда.** Вызови `{"command": "done"}` когда всё готово.
3. **`hint` в ошибке** — что исправить (например, «domain not in allowed_domains»).
4. **`next` — источник state.** Если не знаешь, что делать — смотри `next`.

---

ЧАСТЬ 2: РОЛИ (whitelist)

Запрос: `{"command": "<cmd>", "args": {...}, "role": "<role>"}`. По умолчанию `role="*"` (полный whitelist, для человека/отладки).

| Роль | Назначение | Основные команды |
|---|---|---|
| `parser` | Разбор брифа | `*:list` (read-only) |
| `inspector` | Диагностика состояния | `*:list`, `page:read`, `structure:check` |
| `planner` | План структуры (LLM) | `*:list`, `page:read` |
| `architect` | Каркас: домен, алиас, блоки, схемы, филлеры | `domain:new`, `allias:new`, `block:new`, `scheme:new`, `filler:new`, `*:list` |
| `executor` | Контент страниц | `page:new`, `page:write`, `image:search`, `image:download`, `*:list` |
| `designer` | Стили, вёрстка | `page:write`, `block:edit`, `filler:edit`, `*:list` |
| `bundler` | Сборка CSS/JS | `structure:check`, `*:list` |
| `corrector` | Исправление failed | `page:write`, `structure:repair`, `structure:check` |
| `reporter` | Финальный отчёт | `*:list`, `page:read`, `structure:check` |

Все роли дополнительно получают: `ai:start`, `done`, `engine_status`.

Запрещено всем ролям: `user:new`, `db:migrate`, `admin:passwd`, `domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `trash:clear`, `migrate:page-rules`, `content:*` (запись только через `image:download`).

---

ЧАСТЬ 3: СТАРТ (ai:start)

Первый вызов сессии:
```json
{"command": "ai:start", "role": "architect"}
```
Ответ:
```json
{
  "ok": true,
  "data": {
    "state": {"domains": ["damoony.ru", "damoony3test.ru"]},
    "allowed_domains": ["damoony3test.ru", "damoony.ru"],
    "whitelist": ["ai:start", "done", "engine_status", "domain:new", ...]
  },
  "role": "architect",
  "next": [
    {"cmd": "allias:new", "args": {"domain": "<choose>", "name": "<name>"}, "hint": "..."},
    {"cmd": "domain:new", "args": {"domain": "<new_fqdn>"}, "hint": "..."}
  ]
}
```

`state.domains` — существующие домены. `allowed_domains` — что можно создавать. `whitelist` — твои команды.

---

ЧАСТЬ 4: КОМАНДЫ (кратко, для architect/executor)

**architect:**
```
domain:new --domain=example.ru
allias:new --domain=example.ru --name=coffee
block:new --domain=example.ru --name=top --type=visual
scheme:new --domain=example.ru --name=coffee
filler:new --domain=example.ru --allias=coffee --block=top
```

**executor:**
```
page:new --domain=example.ru --allias=coffee --name=about
page:write --domain=example.ru --allias=coffee --name=about --json='[...]'
  (для главной: --name='__root__')
image:search --query="coffee cup" --count=3 [--provider=wikimedia]
image:download --url=<url> --domain=example.ru --allias=coffee --category=coffee --alt="..."
page:read --domain=example.ru --allias=coffee --name=about
```

**inspector (read-only):**
```
domain:list
allias:list --domain=example.ru
page:list --domain=example.ru --allias=coffee
scheme:list --domain=example.ru
block:list --domain=example.ru
filler:list --domain=example.ru --allias=coffee
engine_status
```

Все `*:list` возвращают `{rows: [...]}` в `data` — используй структуру, не текст.

---

ЧАСТЬ 5: ПРАВИЛО 3 ФАЙЛОВ

Каждая сущность = 3 файла с единым префиксом:
- Страница: `algoritm_{name}.php` + `.rules.json` + `.editor.json`
- Блок: `{name}.php` + `.rules.json` + `.editor.json`
- Схема: `{name}.php` + `.rules.json` + `.editor.json`

Для страниц все три в одной папке. Root — `algoritm/algoritm.{php,rules.json,editor.json}`.

Ты не создаёшь файлы напрямую — только через CLI. CLI генерирует все три.

---

ЧАСТЬ 6: КОНТЕНТ (page:write)

⚠️ **Ты НЕ ПИШЕШЬ PHP-КОД.** Ты передаёшь JSON-массив блоков, PageCompiler компилирует безопасно.

Поддерживаемые типы блоков:
- `h1`, `h2`, `h3` — заголовки
- `text` — параграф
- `img` — картинка (используй `local_url` из `image:download`!)
- `link`, `button` — ссылки
- `ul` — список (`items: [...]`)

Опциональные атрибуты: `class`, `id`, `style`, `url`, `alt`.

Пример:
```json
[
  {"type": "h1", "content": "О нас", "style": "color:#2c3e50;"},
  {"type": "text", "content": "Наш сайт посвящён кофе."},
  {"type": "img", "content": "/content/example.ru/coffee/about/a3f5c1e9.jpg", "alt": "Coffee cup"},
  {"type": "ul", "items": ["Пункт 1", "Пункт 2"]}
]
```

Правила:
- **Нет висячих запятых** (Qwen 7B часто ошибается — перепроверь).
- **Имена страниц/алиасов:** `^[a-z][a-z0-9_]*$`. Без дефисов. Без пробелов.
- **Главная:** `__root__` (не `home`, не `/`). `--parent` несовместим с `__root__`.
- **Картинки:** только `local_url` из `image:download`, никогда внешний URL.

---

ЧАСТЬ 7: КАРТИНКИ (image:*)

1. `image:search` → `{results: [{url, preview_url, width, height, license, author, source, title}]}`
2. Выбери url (width/height ≥ 800, license CC/PD)
3. `image:download` → `{local_url, hash, mime, width, height, size}`. `local_url` = `/content/<domain>/<allias>/<category>/<hash>.<ext>`
4. Вставь `local_url` в `page:write` как `{"type":"img","content":"<local_url>"}`

Провайдеры: `wikimedia` (default, без ключа), `openverse` (fallback). Автофоллбэк работает только для default (при явном `--provider=` — без fallback).

---

ЧАСТЬ 8: КОНТЕКСТНЫЕ ШАГИ (по ролям)

**Architect:**
```
ai:start → domain:new → next: [allias:new, done]
allias:new → next: [page:new (для executor), skin:new (P2), done]
```

**Executor:**
```
page:new → next: [page:write, done]
page:write → next: [page:new, page:read, done]
```

**Если next пуст** (или `done`) — работа роли завершена, передавай управление координатору (вне AI-цикла).

---

ЧАСТЬ 9: БЕЗОПАСНОСТЬ

- **Domain whitelist (agent.config):** domain должен быть в `allowed_domains`. Иначе — `Security violation`.
- **URL whitelist (ainet.config):** url в `image:download`/`fetch` должен быть в `allowed_domains`. Иначе — `Domain 'X' not in whitelist`.
- **Path traversal:** regex валидация всех `--domain`/`--name`. `..` отклоняется.
- **RCE закрыт:** PageCompiler использует `Builder` + `var_export`. Сырой PHP не проходит.
- **Ты не имеешь доступа к:** `user:new`, `db:migrate`, `admin:passwd`, destructive-командам, `content:*` (запись).

---

ЧАСТЬ 10: ПРИМЕР ПРОГОНА (architect)

Шаг 1: `{"command": "ai:start", "role": "architect"}` → next: domain:new
Шаг 2: `{"command": "domain:new", "args": {"domain": "example.ru"}, "role": "architect"}` → next: allias:new
Шаг 3: `{"command": "allias:new", "args": {"domain": "example.ru", "name": "coffee"}, "role": "architect"}` → next: page:new, done
Шаг 4: `{"command": "done", "role": "architect"}` → exit 0

**Executor** получает управление, `page:new` / `page:write` для каждой страницы.

---

ЧЕК-ЛИСТ ПЕРЕД СДАЧЕЙ
1. Ты передал JSON-массив (не PHP).
2. Нет висячих запятых.
3. Имена без дефисов.
4. Для главной — `__root__`, без `--parent`.
5. Для картинки: `image:search` → `image:download` → `local_url` → `page:write`.
6. `alt` передан в `image:download`.
7. `domain` — из `allowed_domains`.
8. Команда — из твоего `whitelist` роли.
9. Если `ok:false` — прочитай `hint`, исправь.
10. Если `next` содержит `done` и работа завершена — вызови `done`.

ССЫЛКИ
MANIFEST.md — канон (✅/🟡/❌, P0/P1/P2).
agent_architecture.md — Navigator, роли, tasks.
cli.md — полный справочник команд.
architecture.md — жизненный цикл (включая AI-слой).
ainet.md — дизайн AINET (SSRF, провайдеры, конфиг).
contracts.md — контракты и ограничения.

--- КОНЕЦ ДОКУМЕНТА ---