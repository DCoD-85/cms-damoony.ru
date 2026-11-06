# AI-Слой: Интеграция, AINET и Мультиагентная генерация

Точка входа: `bin/aicli.php` (JSON-in/out). Три независимых контура:

1. **aicli** (`bin/aicli.php`) — внешний JSON-in/out, 9 ролей, HATEOAS, AINET. Этот документ.
2. **Orchestrator** (`src/Ai/Orchestrator/`) — внутренний AIUI (Interviewer → Architect → Coder, Blackboard). Описан в `orchestrator.md`, `aiui.md`.
3. **brain.py** (`ai_agent/brain.py`) — Python-оркестратор Wizard (генерация «с нуля»). Описан в §6–§7.

## 1. Архитектура и Контракт (HATEOAS)

AI не имеет прямого доступа к терминалу. Отправляет JSON в STDIN `bin/aicli.php`, получает JSON в STDOUT.

**Запрос:**
```json
{"command": "domain:new", "args": {"domain": "example.ru"}, "role": "architect"}
```
`role` опционален, default — `*`.

**Ответ:**
```json
{
  "ok": true,
  "data": {...},
  "error": "", "hint": "",
  "role": "architect",
  "next": [{"cmd": "allias:new", "args": {"domain": "example.ru", "name": "<name>"}, "hint": "Создать алиас"}]
}
```

**Правила:**
1. `next[]` — HATEOAS-подсказка (`Navigator::after()`). Агент может игнорировать и вызвать любую команду из своего whitelist.
2. `done` — доступен всегда, завершает сессию.
3. `ok: false` → `hint` содержит инструкцию для самокоррекции.

## 2. Роли (Whitelist)

9 ролей + `*`. Полный whitelist — `src/Ai/Roles.php`.

| Роль | Назначение (compact) |
|---|---|
| `parser` | Разбор брифа: `*:list` |
| `inspector` | Диагностика: `*:list`, `structure:check`, `db:schema/methods/describe/call` |
| `planner` | План: `*:list`, `structure:check` |
| `architect` | Каркас: `domain:new`, `allias:new`, `block:new`, `scheme:new`, `filler:new`, `skin:new`, `dataprovider:add-method` |
| `executor` | Контент + БД: `page:new`, `page:write`, `image:search/download`, `db:call`, `db:mutate --force` |
| `designer` | Стили + JSON: `skin:edit`, `block:write`, `filler:write`, `scheme:save`, `page:write` |
| `bundler` | Сборка: `structure:check` |
| `corrector` | Исправления: `page:write`, `structure:repair`, `structure:check` |
| `reporter` | Отчёт: `*:list`, `structure:check`, `db:call` |

**`wizard:*`:** start/domain/allias/plan/abort → architect; content/finish → executor; skin → designer.

**Роль `*`:** объединяет все 9 + специфичные: `wizard:*`, `agent:chat`, `ai:start`, `done`, `engine_status`, `net:*`, `image:search/download`, `fetch`, `block:write`, `filler:write`.

**Запрещено всем:** `user:new`, `db:migrate`, `admin:passwd`, destructive (`*:remove`, `trash:clear`, `content:remove`).

**Известные полуреализации:**
- `wizard:abort` — в `Roles.php` есть (architect, `*`), `case` в `WizardManager::handle()` нет → `Unknown wizard action`.
- `wizard:widgets` — реализован в `WizardManager`, но отсутствует в `Roles.php` → недоступен через aicli.

**DataProvider-инвариант:** `db:*` — только через `bin/aicli.php`/`brain.py`, не через Orchestrator/AIUI (см. `arch.md` §5).

## 3. Navigator (Граф состояний)

`src/Ai/Navigator.php` формирует `next[]` из текущего состояния.

- **OFFERABLE (10 команд):** `domain:new`, `allias:new`, `skin:new`, `skin:edit`, `page:new`, `page:write`, `page:read`, `image:search`, `image:download`, `done`.
- **`start(state)`** — начальные подсказки (1–2): нет доменов → `domain:new`; иначе `allias:new` + `domain:new`.
- **`after(cmd)`** — match по 9 командам: `domain:new` → `allias:new`+`done`; `page:new` → `page:write`+`image:search`+`done`; `page:write` → `page:new`+`page:read`+`done`; `done` → `[]`. Полный граф — `Navigator::after()`.

`next[]` фильтруется по whitelist роли.

## 4. Безопасная генерация контента (Компиляторы)

⚠️ **AI не пишет PHP.** Отдаёт JSON через `page:write`/`block:write`/`filler:write`.

**Типы блоков** (по `BlockCodegen`): базовые `h1`, `h2`, `h3`, `text`, `img`, `link`, `button`, `ul`, `nav`; Phase 4.2 расширила `section`, `div`. Точный список — `src/Ui/BlockCodegen.php`.
**Атрибуты:** `class`, `id`, `style`, `url`, `alt`.

**Builder** (`src/Ui/Builder.php`) — 12 методов: `el`, `elA`, `div`, `span`, `p`, `h`, `a`, `img`, `input`, `ul`, `li`, `button`. Нет `section`/`form`/`table`/`select`/`textarea`/`label`/`nav` — обход через `Builder::el($tag)` (P2, `arch.md` §6 п.4).

**XSS:** `PageCompiler`/`BlockCodegen` используют `Text::raw()` для `<a>`/`<strong>`, жёстко фильтруя остальные теги. `allow_php_blocks=false` по умолчанию — блоки с сырым PHP запрещены.

## 5. AINET (Выход в интернет)

Библиотека `src/Ainet/`. AINET не пишет на диск — передаёт байты в CLI (`content:store`).

### Команды

- `net:status` — диагностика (провайдеры, лимиты, кэш).
- `image:search --query="..." --count=5` — поиск картинок. Провайдеры: `wikimedia` (default), `openverse` (fallback). Unsplash/Pexels — ключи в config пусты, провайдер не реализован.
- `image:download --url="..." --domain=[d] --allias=[a] --category=[c]` — скачать в `/content/`. Возвращает `local_url`.
- `fetch --url="..." --type=json|text` — GET-запрос. **Единственная команда без префикса `net:`** (исторически).

### SSRF-защита (`src/Ainet/UrlGuard.php`)

10-шаговая валидация (в порядке):
1. URL ≤ 4096 chars, парсится `parse_url`.
2. Scheme ∈ `allowed_schemes` (config: `["https"]`; код default — `http,https`).
3. Host не пустой.
4. Нет `user:pass` в URL.
5. Port ∈ `allowed_ports` (80, 443).
6. Прямой IP → private IP check ДО whitelist.
7. Host ∈ whitelist (точный матч, без wildcard).
8. Query ≤ 2048.
9. Hostname → DNS → private IP check.
10. Fragment отрезается.

Редиректы — ручные (`CURLOPT_FOLLOWLOCATION=false`), повторный `UrlGuard` на каждый `Location`.

### Конфиг (`config/ainet.config`)

- **Whitelist (11 доменов):** openverse, unsplash, pexels, wikimedia, wikipedia, github.
- **Providers:** `default=wikimedia`, `keys.unsplash=""`, `keys.pexels=""`.
- **Limits:** `max_size=25MB`, `max_redirects=3`, `rate_per_min=30`, `min_image_dimension=200`.
- **Cache:** `cache_ttl_days=7`.
- **MIME:** `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`.

URL whitelist независим от domain whitelist (`agent.config`).

## 6. Мультиагентная генерация (brain.py)

`ai_agent/brain.py` — Python-оркестратор Wizard. **Запуск — CLI или через Web UI (§7):**
```
python -m ai_agent.brain <domain> <allias> "<brief|@file>" <model> [--session=ID]
```

5 шагов (по логу):
1. `[1/5] Wizard started` — инициализация, preflight.
2. `[2/5] Architect is planning the structure...` — LLM генерирует `{pages: [{name, title, parent}]}`. name — английский, title — русский.
3. `[*] Generating global widgets...` — LLM генерирует глобальные блоки (left/right/bottom/policy).
4. `[3/5] Generating content (Markdown mode)...` — per-page контент через Markdown-парсер → JSON-блоки. + auto-detect форм (`needs_form`, `fields`).
5. `[4/5] Designer is creating main.css...`, `[5/5] Designer is styling top.css...` — 2 CSS-файла (base+layout и меню).

**Детерминированные функции** (не LLM):
- `validate_plan(plan)` — topo sort, drop orphan parent, sanitize, cycle guard.
- `_safe_href(url)` — валидация href перед `<a>` (блокирует `javascript:`, `data:`, `vbscript:`, `file:`, `//evil`).
- `_sanitize_name(name)` — санитизация имени страницы (path traversal guard).

**Проверка без LLM:** `python _dev/smoketests/test_brain_safety.py` (23 теста).

## 7. AI Wizard Web UI (алиас `agent`)

Web UI — `app/[domain]/agent/algoritm/ajax/algoritm_ajax.php` (наследник `Damoony\Algoritm\Page`). 4 action:

### `action=chat`
```
POST {action: "chat", history: "[...]"}
→ bin/aicli.php {command: "agent:chat", role: "*", args: {history}}
→ JSON
```
Интервьюер. История — JS-массив `chatHistory` (браузер).

### `action=generate`
```
POST {action: "generate", history: "[...]"}
→ PHP: brief = "Клиент: ...\nДизайнер: ..." из history
→ var/{session_id}_brief.txt
→ Windows: var/{session_id}_run.bat + cmd /c start /B "..." 
  Linux: nohup {python} -u -m ai_agent.brain {domain} auto @{brief_file} --session={session_id} > {log} 2>&1 &
→ JSON {ok: true, session_id: "gen_XXXX"}
```
- `domain` — из `config/agent.config` (`target_domain`) или из `HTTP_HOST` (без `agent.`).
- `allias=auto` — Python сам придумает.
- `python` — из `config/agent.config` (`python_path`) или `python`.
- Домен добавляется в `agent.config.allowed_domains` (авто).

### `action=status`
```
POST {action: "status", session_id: "gen_XXXX"}
→ читает var/{session_id}_brain.log
→ парсит последнюю строку из [1/5]... [=>], -> Writing:
→ JSON {ok, done: bool, status_text | link}
```
Маппинг → UI-статус:
- `[1/5] Wizard started` → «Инициализация...»
- `[2/5] Architect is planning...` → «Проектирование структуры...»
- `[*] Generating global widgets...` → «Генерация виджетов...»
- `[3/5] Generating content...` → «Генерация контента...»
- `[4/5] Designer is creating main.css...` → «Создание стилей (main.css)...»
- `[5/5] Designer is styling top.css...` → «Настройка навигации (top.css)...»
- `[=>] All drafts collected...` → «Сборка файлов на диске...»
- `-> Writing:` → «Написание текста для:»

**Финал:** `Done! Total time:` + `SITE_URL: http...` → `{done: true, link: ...}`.

**Ошибки:** `Fatal error` / `Aborting session` / «уже занят» → `{ok: false, error: "..."}`. Таймаут: лог 0 байт > 180 сек → «Модель не отвечает».

### `action=cancel`
```
POST {action: "cancel", session_id: "gen_XXXX"}
→ file_put_contents(var/{session_id}.cancel, "1")
→ JSON {ok: true}
```
`brain.py` проверяет `.cancel` между задачами, при нахождении — `Aborting session` → status покажет `Отменено пользователем`.

**Таймауты:** 600s (`php.ini max_execution_time`, `nginx.conf fastcgi_read_timeout`).

**JS:** встроен в `algoritm_ajax.php` (не отдельный файл). `aiui.js` — это для AIUI (`/admin/aiui.php`), не для Wizard.

## 8. ModelRouter и Orchestrator

### 8.1 ModelRouter

`src/Ai/ModelRouter.php` — выбор модели по роли. Приоритет: `roles[role]` → `default` → `qwen2.5-coder:7b`.

`config/models.config` (трекается в git, секретов нет):
- `default: "deepseek-coder:6.7b"`
- `reporter: "gemma4:e4b"` (чат, брифы)
- `architect: "deepseek-coder:6.7b"` (JSON, план)
- `designer: "gemma4:e4b"` (JSON-блоки)
- `parser/inspector/planner/executor/bundler/corrector: "deepseek-coder:6.7b"`

Ключи с `_` (например `_readme`) игнорируются кодом.

Приоритет в Orchestrator: `args['model']` → `ModelRouter::resolve($role)` → `agent.config.llm_model`.

### 8.2 brain.py vs Orchestrator

| Контур | Где | Вход | Выход | Модели |
|---|---|---|---|---|
| **aicli** | `bin/aicli.php` | JSON (stdin) | JSON (stdout) | Ollama/DeepSeek/Cloud (`agent.config`) |
| **Orchestrator** | `src/Ai/Orchestrator/` | prompt (AIUI) | blocks → `.sess_*/context.json` | ModelRouter |
| **brain.py** | `ai_agent/brain.py` | brief (CLI или `@file`) | страницы в `app/` | Ollama (deepseek/gemma) |

Orchestrator — не CLI. Живёт в памяти PHP-запроса `POST /admin/ai_edit.php`, использует `AicliClient` (PHP-класс) для `bin/cli.php`. Состояние — `context.json` в `.sess_*`.

brain.py — независимый Python-процесс, вызывает `bin/aicli.php` через subprocess. **Не связан** с Orchestrator. Детали — `orchestrator.md`.

## 9. Связанные документы

- `_doc/orchestrator.md` — внутренний мультиагентный конвейер AIUI (Blackboard, AgentInterface, ModelRouter, 4 action AIUI, build_progress).
- `_doc/aiui.md` — UI-документация AIUI (кнопки, панели, сессия).
- `_doc/arch.md` §4 — контракты Orchestrator и ModelRouter.
- `_doc/cli.md` — файловые команды (`bin/cli.php`), которые вызывает aicli.
- `_doc/dev.md` — как писать страницы/блоки.