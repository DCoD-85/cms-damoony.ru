> ⚠️ **СТАТУС: ЗАМОРОЖЕНО (FROZEN).** Интеграция ИИ отложена до перестройки ядра (UI Elements / Админка / БД). Не вносить изменения без разморозки.

# Orchestrator: внутренний мультиагентный конвейер AIUI

Документ описывает `src/Ai/Orchestrator/` — внутренний AI-контур для AIUI (редактор страниц).
Не путать с `bin/aicli.php` (внешний JSON-in/out для brain.py) и `ai_agent/brain.py` (Python-оркестратор Wizard).

См. также: `_doc/arch.md` §4 (контракты), §6 (P2), `_doc/aiui.md` (UI), `_doc/ai.md` §8 (связь с AIUI).

---

## 1. Назначение и отличие от соседних контуров

| Контур | Где | Что делает | Модели |
|---|---|---|---|
| `bin/aicli.php` | внешний CLI | JSON-in/out, роли, HATEOAS, AINET | Ollama/DeepSeek API/Cloud.ru (agent.config) |
| `ai_agent/brain.py` | Python | Wizard: генерация сайта с нуля | Ollama (qwen2.5-coder) |
| **`Orchestrator`** | PHP, `src/Ai/Orchestrator/` | **AIUI**: редактирование существующего сайта через чат + многостраничная генерация | Ollama (gemma4:e4b, deepseek-coder:6.7b) через ModelRouter |

**Ключевое отличие**: Orchestrator — не CLI. Он живёт в памяти PHP-запроса `POST /admin/ai_edit.php`, использует AicliClient (PHP-класс) для вызова `bin/cli.php`, и хранит состояние в `context.json` внутри папки сессии (`.sess_*`).

---

## 2. Архитектура (Blackboard + State Machine)

Текстовая схема потока данных:

```text
User prompt
   │
   ▼
[ ContextManager  (Blackboard = .sess_*/context.json) ]
   ├── meta        { status, iteration, build_progress }
   ├── input       { prompt, brief, current_blocks, history }
   ├── tools_context { db_tables, structure_ok, errors }
   └── agent_outputs { interviewer, architect, coder }
   │
   ▼
InterviewerAgent ──(если action=chat)──> Ответ в UI (без записи файлов)
   │
   ▼ (если action=build)
ArchitectAgent (создает pages[]) ──> CoderAgent (генерирует блоки для каждой)
```

**State Machine (статусы meta.status):**

- Инициализация: `init` -> `tools_collected`
- Чат: `chat_done` (конвейер останавливается, ждем пользователя)
- Сборка (кнопка): `build_started` -> `architect_done` -> `[per-page]` -> `build_done` (или `build_partial`, если были ошибки)

---

## 3. Компоненты

### 3.1. ContextManager (`src/Ai/Orchestrator/ContextManager.php`)

Единственный владелец `context.json`. Все агенты **не пишут** файл напрямую — только через ContextManager.

Методы:
- `init(prompt, targetBlock, currentBlocks)` — создать или идемпотентно прочитать.
- `updateInput(prompt, currentBlocks)` — обновить `input.prompt` перед вызовом агента.
- `read()` / `save(ctx)` — атомарное чтение/запись.
- `updateAgentOutput(agent, data)` — записать `agent_outputs[agent]`.
- `addHistory(role, content)` — добавить реплику в `input.history`.

### 3.2. ToolsCollector (`src/Ai/Orchestrator/ToolsCollector.php`)

Read-only снимок окружения до вызова LLM. Через AicliClient::runJson():
- `structure:check --domain=X` — ошибки структуры (фильтруются state machine по домену).
- `db:schema --domain=X --alias=Y` — CLI возвращает таблицы **с колонками** (`information_schema.COLUMNS`); `ToolsCollector` оставляет только `name` (экономия контекста LLM).

Возвращает массив:
- structure_ok (bool)
- structure_errors (list)
- db_tables (list)
- errors (list)

Реализует ToolsCollectorInterface (для тестируемости — fake в unit-тестах).

### 3.3. ModelRouter (`src/Ai/ModelRouter.php`)

Читает `config/models.config`, кеширует в памяти.
- `resolve(role)` — модель для роли. Приоритет: `roles[role]` -> `default` -> `qwen2.5-coder:7b`.

Пример `models.config`:

```json
{
  "default": "deepseek-coder:6.7b",
  "roles": {
    "reporter": "gemma4:e4b",
    "architect": "deepseek-coder:6.7b",
    "designer": "deepseek-coder:6.7b"
  }
}
```

Ключи с нижним подчеркиванием (`_`) игнорируются (документация внутри файла, коммитится в git — секретов нет).

### 3.4. Orchestrator (`src/Ai/Orchestrator/Orchestrator.php`)

Тонкий диспетчер. Конструктор принимает: ContextManager, ToolsCollectorInterface, InterviewerAgentInterface, ArchitectAgentInterface, CoderAgentInterface, AicliClient.

Два публичных метода:
- `run()` — интерактивный (AIUI `action=preview`).
- `build()` — пакетный (AIUI `action=build`).

### 3.5. AgentInterface (`src/Ai/Orchestrator/Agent/AgentInterface.php`)

Метод `execute(array $context): array`. Возвращает данные для записи в `agent_outputs[agentName]`. Каждый агент также реализует свой интерфейс для fake в тестах.

---

## 4. Агенты

### 4.1. InterviewerAgent (роль reporter, gemma4:e4b)

**Задача**: общаться, накапливать бриф.

- Если prompt содержит «создава / генерир / примен / поехали / старт», возвращает `action: build` **без вызова LLM** (жёсткий PHP-перехват через mb_stripos).
- Иначе вызывает LLM с историей и системным промптом. Ответ парсится как JSON: `{message: текст, brief: текст}`.
- Если LLM вернул не-JSON, fallback: возвращает action: chat, message: reply, brief: currentBrief.
- brief может прийти массивом (LLM капризна) — конвертируется в строку через implode.

### 4.2. ArchitectAgent (роль architect, deepseek-coder:6.7b)

**Задача**: разбить бриф на страницы. Возвращает **строго** `pages: [...]`.

Пример ответа:

```json
{
  "pages": [
    {
      "name": "home",
      "path": "",
      "parent": null,
      "title": "Главная",
      "brief": "Что должно быть на странице",
      "sections": ["h1", "text", "section"]
    }
  ]
}
```

Правила: name — snake_case, path — без слэша на старте, parent — null или имя родителя.

### 4.3. CoderAgent (роль designer, deepseek-coder:6.7b)

**Задача**: сгенерировать JSON-блоки для **одной** страницы.

- `execute(context)` — обратная совместимость: берёт `architect.pages[0]` и вызывает executeForPage.
- `executeForPage(context, pagePlan)` — основной вход из Orchestrator::build().

Возврат: `blocks: [...]`.

**Нормализация (в normalizeBlock):**
- Если content — массив, перекладывается в children.
- stripMarkdown() снимает звездочки, бэктики, скобки markdown-ссылок и решетки заголовков.
- Рекурсия по children.

---

## 5. context.json — схема

Файл пишется в `app/.sess_{tab}.{realDomain}/context.json`.

Структура:
- **meta** (status, iteration, build_progress[], pages_total, pages_ok, errors[])
- **input** (prompt, brief, current_blocks[], history[])
- **tools_context** (structure_ok, structure_errors[], db_tables[], errors[])
- **agent_outputs** (interviewer, architect, coder)

Пример build_progress:
`[ {page: "home", status: "done", blocks: 1}, {page: "menu", status: "failed", error: "..."} ]`

---

## 6. run() — интерактивный режим (AIUI action=preview)

Порядок вызовов:
1. ContextManager::init()
2. ContextManager::updateInput()
3. ToolsCollector::collect()
4. InterviewerAgent::execute()
   -> если action=chat: meta.status = chat_done; return (UI показывает reply + brief)
   -> если action=build: продолжить
5. ArchitectAgent::execute() -> meta.status = architect_done
6. CoderAgent::execute() -> meta.status = coder_done

**Ошибки**: каждый шаг в try/catch. При падении Interviewer/Architect — return (конвейер прерывается). При падении Coder — не прерывается (чтобы вернуть хотя бы план).

Вызывается из `ai_edit.php`, `action=preview`.

---

## 7. build() — постраничная генерация (AIUI action=build)

Порядок действий:
1. Прочитать context.json. Если input.brief пуст -> build_failed.
2. input.prompt = input.brief; meta.status = build_started
3. ArchitectAgent::execute -> meta.status = architect_done
   -> если pages пуст -> build_failed
4. foreach pages as pagePlan:
   a. page:new --session=X --name=pagePlan.name (если не root)
   b. CoderAgent::executeForPage() -> blocks
   c. page:write --session=X --name=... --json_file=... --with_schema=1
   d. meta.build_progress[i] = {page, status: done|failed, blocks|error}
   e. save(ctx) (после каждой страницы)
5. meta.status = build_done (если все ok) или build_partial (если были failed)

**Изоляция ошибок**: try/catch на каждую страницу. Падение одной страницы не блокирует остальные. Результат — build_partial и build_progress с деталями.

**Важно**: page:new и page:write вызываются с флагом `--session=X`. Без этого флага они пишут в production.

---

## 8. AIUI endpoint (www/admin/ai_edit.php) — 4 action

- **start**: SessionStartCommand (или discard + start). Возврат: ok, tab, preview_url.
- **preview**: Orchestrator::run(). Возврат: chat_only (reply, brief) или preview_url, pages_total, pages_ok, progress.
- **build**: Orchestrator::build(). Возврат: preview_url, pages_total, pages_ok, progress.
- **apply**: SessionApplyCommand (проверка: рекурсивный countBlocks() >= 5). Возврат: ok, applied.

**CSRF / auth**: проверка ADMINSESSID и admin_login в начале файла. Все JSON-ответы — с JSON_INVALID_UTF8_SUBSTITUTE.

**Таймауты** (Windows PHP-CGI):
- php.ini: max_execution_time=600, max_input_time=600
- nginx.conf: fastcgi_read_timeout 600s, fastcgi_send_timeout 600s
- AgentChatHandler: CURLOPT_TIMEOUT=600

---

## 9. Инварианты

1. **AI не пишет PHP.** Только JSON через page:write, block:write, filler:write.
2. **Файловые операции — только через CLI.** Orchestrator вызывает bin/cli.php через AicliClient. Никаких file_put_contents в src/Ai/Orchestrator/.
3. **Сессии изолированы.** Все page:new и page:write — с флагом --session=X. Без флага — production.
4. **context.json — единственный source of truth** для state.
5. **Никакого rollback.** Падение Coder на одной странице -> страница failed в build_progress, остальные продолжаются.
6. **Модели — только через ModelRouter.**

---

## 10. P2 / ограничения

Полный список — `_doc/arch.md` §6 (пункты 1, 9-14 касаются Orchestrator напрямую). Краткая сводка:

- **Архитектор нестабилен** (п.9): разные имена страниц в разных запусках.
- **Coder wrap** (п.10): LLM часто возвращает `section` с children; `build_progress.blocks` считает только верхний уровень.
- **`PageNewCommand --session`** (п.11): на Windows (php-cgi) требует рестарт opcache.
- **DesignerAgent / WriterAgent / CriticAgent** отсутствуют (п.12): CSS не генерируется, тексты — CoderAgent, валидация — детерминированная (`page:write` + `PageCompiler`).
- **Нет `build:rollback`** (п.1).

Тесты — `_dev/tests/Unit/Ai/Orchestrator/`, запуск — `composer test`.
