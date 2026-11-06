# AI-Слой: Интеграция, AINET и Мультиагентная генерация

Этот документ описывает **внешний** AI-слой: `bin/aicli.php` (JSON-in/out для `brain.py` и сторонних агентов), роли, Navigator, AINET. Для полной автономной сборки сайтов используется Python-оркестратор `ai_agent/brain.py`.

**Три независимых AI-контура:**

1. **`bin/aicli.php`** — внешний JSON-in/out. Роли (`Roles.php`), HATEOAS (`Navigator`), AINET (`net:*`, `image:*`). Этот документ.
2. **`src/Ai/Orchestrator/`** — внутренний мультиагент для AIUI (Interviewer → Architect → Coder, Blackboard, `ModelRouter`). **Не использует** `bin/aicli.php`, работает через `AicliClient` → `bin/cli.php`. Описан в `_doc/orchestrator.md`.
3. **`ai_agent/brain.py`** — Python-оркестратор для Wizard (генерация «с нуля»). Использует `bin/aicli.php` + Ollama. Описан в §6 и `_doc/install.md`.

---

## 1. Архитектура и Контракт (HATEOAS)

AI не имеет прямого доступа к терминалу. Он отправляет JSON в STDIN `bin/aicli.php` и получает JSON в STDOUT.

**Запрос:**
```json
{
  "command": "domain:new",
  "args": {"domain": "example.ru"},
  "role": "architect"
}
```
*`role` — опционально, по умолчанию `*`.*

**Ответ:**
```json
{
  "ok": true,
  "data": {"...": "результат"},
  "error": "", "hint": "",
  "role": "architect",
  "next": [
    {"cmd": "allias:new", "args": {"domain": "example.ru", "name": "[name]"}, "hint": "Создать алиас"},
    {"cmd": "done", "args": [], "hint": "Завершить"}
  ]
}
```

**Правила:**
1. `next[]` — это HATEOAS-подсказка (что логично сделать дальше). Агент может игнорировать её и вызвать любую команду из своего whitelist.
2. `done` — доступен всегда. Завершает сессию.
3. Если `ok: false`, поле `hint` содержит инструкцию для самокоррекции.

---

## 2. Роли (Whitelist)

Роль ограничивает доступные команды (defense in depth). Реализация: `src/Ai/Roles.php`.

| Роль | Назначение | Основные команды |
|---|---|---|
| `parser` | Разбор брифа | `*:list` (read-only) |
| `inspector` | Диагностика | `*:list`, `page:read`, `structure:check` |
| `planner` | План структуры | `*:list`, `page:read` |
| `architect` | Каркас сайта | `domain:new`, `allias:new`, `block:new`, `scheme:new`, `filler:new`, `*:list` |
| `executor` | Контент | `page:new`, `page:write`, `image:search`, `image:download`, `*:list` |
| `designer` | Стили | `page:write`, `block:write`, `filler:write`, `skin:edit`, `*:list` |
| `bundler` | Сборка | `structure:check`, `*:list` |
| `corrector` | Исправления | `page:write`, `structure:repair`, `structure:check` |
| `reporter` | Отчёт | `*:list`, `page:read`, `structure:check` |

**Роль `*` (по умолчанию):** объединяет все 9 ролей и добавляет специфичные команды (`net:*`, `skin:remove`, `block:write`, `filler:write`, `agent:chat`). Также включает все команды `wizard:*`.

**Запрещено всем:** `user:new`, `db:migrate`, `admin:passwd`, а также любые destructive-команды (`*:remove`, `trash:clear`, `content:remove`).

---

## 3. Navigator (Граф состояний)

Скрипт `src/Ai/Navigator.php` формирует массив `next[]` на основе текущего состояния.

- `OFFERABLE` = `[domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, image:search, image:download, done]`.
- `next[]` фильтруется по whitelist роли.

---

## 4. Безопасная генерация контента (Компиляторы)

⚠️ **AI НЕ ПИШЕТ PHP-КОД.** Он передаёт JSON-массив блоков через `page:write`, `block:write` или `filler:write`.

**Поддерживаемые типы:** `h1`, `h2`, `h3`, `text`, `img`, `link`, `button`, `ul`, `nav`.
**Атрибуты:** `class`, `id`, `style`, `url`, `alt`.

*Особенность:* `PageCompiler` и `BlockCompiler` используют `Text::raw()` для безопасного вывода тегов `<a>` и `<strong>` (сгенерированных Markdown-парсером), но жестко фильтруют остальные теги (`strip_tags`), исключая XSS.

---

## 5. AINET (Выход в интернет)

Библиотека `src/Ainet/` позволяет AI искать картинки и получать данные. **AINET не пишет на диск** — он передаёт байты в CLI (`content:store`).

### Команды:
- `net:status` — диагностика (провайдеры, лимиты, кэш).
- `image:search` — поиск картинок (`--query="..." --count=5`). Провайдеры: `wikimedia` (default), `openverse` (fallback).
- `image:download` — скачивание картинки в `/content/`. Возвращает `local_url`.
- `fetch` — GET-запрос к API (`--url="..." --type=json|text`).

### Безопасность (SSRF):
- Только GET-запросы.
- Whitelist URL-доменов в `config/ainet.config` (без wildcard). Независим от `agent.config`.
- Блокировка private IP (`127.0.0.1`, `10.0.0.0/8`, AWS metadata `169.254.169.254` и т.д.).
- Ручные редиректы (`CURLOPT_FOLLOWLOCATION=false`), повторный UrlGuard на каждый Location.
- Rate limit: 30 req/min. Кэш: 7 дней.

---

## 6. Мультиагентная генерация (E2E)

Для автономной сборки используется Python-скрипт `ai_agent/brain.py`. Архитектура построена на паттерне Map-Reduce (очистка контекста LLM на каждом шаге):

1. **Architect (Structure):** Создает карту сайта, включая вложенные страницы (через параметр `parent`), и заголовки на языке брифа.
2. **Plan Corrector (Validator):** Детерминированная функция `validate_plan()`. Исправляет ошибки нейросети (например, склеивание имен `care_guide_documentation` -> `documentation`) до отправки в CLI.
3. **Builder:** Вызывает CLI для создания структуры папок (с учетом вложенности), блоков, меню и схемы. Меню автоматически строит правильные URL (например, `/care_guide/documentation`).
4. **Content Workers (Markdown Mode):** LLM генерирует текст в формате Markdown. Python-парсер разбирает его на блоки, конвертирует Markdown-ссылки в безопасный HTML (`<a>`, `<strong>`) и скачивает картинки через AINET.
5. **Modular Designer:** LLM анализирует классы и генерирует два CSS-файла: `main.css` (base + layout + стили центра) и `top.css` (меню). Отдельного `center.css` нет — центральные стили в `main.css`. Базовый layout (сетка, ограничение картинок) зашит в Python-код.

**Запуск:**

    python -m ai_agent.brain <domain> <allias> "<brief|@file>" <model_name> [--session=ID]

---

## 7. AI Wizard (Web UI)

Веб-интерфейс для автономной генерации сайтов: пользователь общается с LLM для сбора брифа, затем асинхронно запускается `ai_agent/brain.py` в фоне с отслеживанием прогресса без перезагрузки страницы.

### 7.1. Карта процессов (Data Flow)

1. **Интервьюер (Frontend):** пользователь общается с LLM через чат. История диалога хранится в JS-массиве `chatHistory` в оперативной памяти браузера.
2. **Триггер (AJAX):** при вводе команды «Создавай» JS отправляет `history` на `algoritm_ajax.php` (`action=generate`).
3. **Background Job (PHP → Python):** PHP собирает бриф в `var/*_brief.txt`, создаёт `.bat`-скрипт и запускает `brain.py` в фоне (через `cmd /c start /B`). AJAX мгновенно возвращает `session_id`.
4. **Multi-Agent (Python):** `brain.py` работает независимо от веб-сервера. Он вызывает `bin/aicli.php` (через `wizard:*`) для безопасной сборки сайта.
5. **Status Polling:** JS каждые 3 секунды опрашивает `algoritm_ajax.php` (`action=status`). PHP читает лог-файл Python (`var/*_brain.log`), парсит текущую стадию (Architect, Content, CSS) и возвращает её фронтенду.
6. **Cancel:** кнопка «Остановить» создаёт файл `var/{session_id}.cancel`. `brain.py` проверяет его наличие между задачами и корректно прерывает работу.
7. **Finish:** при появлении в логе `Done!` JS выводит пользователю кнопку со ссылкой на сгенерированный сайт.

### 7.2. Особенности реализации под standalone-стек (Windows)

Wizard UI (`algoritm_ajax.php`) работает на standalone-стеке **Nginx + PHP-CGI** (см. `_doc/start_new_audit.md` §4). Раньше использовался OSPanel + Apache, но проект отказался от него (проблемы с `mpm_winnt` и `proc_open`). Текущие архитектурные решения:

1. **Изоляция путей:** в `algoritm_ajax.php` жёстко прописаны абсолютные пути к `php.exe` и `python.exe`. Nginx + PHP-CGI не наследует системный `PATH` (в частности, не видит MSYS2 Python).
2. **CLI через `php.exe`, не `php-cgi.exe`:** `AicliClient::run()` проверяет `PHP_BINARY` и заменяет `php-cgi` на `php`. Иначе CLI-команды не выполняются (CGI-режим не читает аргументы как CLI).
3. **Асинхронность:** запуск Python — через временный `.bat`-файл в `var/` и вызов `cmd /c start /B`. Это не блокирует PHP-CGI worker и не вешает Nginx.
4. **Таймауты (600s):** для длинных генераций — `php.ini` (`max_execution_time=600`), `nginx.conf` (`fastcgi_read_timeout 600s`, `fastcgi_send_timeout 600s`), `AgentChatHandler` (`CURLOPT_TIMEOUT=600`).
5. **Отслеживание статуса:** статус определяется парсингом лога `brain.py` на ключевые слова (`[1/5]`, `-> Writing`, `Done!`).

### 7.3. UI/UX (Frontend)

Интерфейс чата реализован на нативном JavaScript (fetch API) без jQuery.

- **Таймер:** засекает время с момента запуска генерации.
- **Сброс контекста:** если пользователь пишет «Удали» или «Сброс», JS очищает массив `chatHistory` и просит LLM начать интервью заново.


---

## 8. AIUI (AI User Interface) — краткий обзор

Полное описание внутреннего конвейера — в `_doc/orchestrator.md`. Здесь — только контракт между AIUI и AI-слоем.

### 8.1. Endpoint: `POST /admin/ai_edit.php` (JSON-in/out)

4 action:

- **`start`** — создать/пересоздать сессию (`SessionStartCommand`). Возвращает `{ok, tab, preview_url}`. Если сессия уже была — сносит в карантин через `SessionDiscardCommand` и создаёт заново.
- **`preview`** — интерактивный шаг: `Orchestrator::run()` (Interviewer → chat или Architect → Coder).
  - Если Interviewer вернул `action=chat`: `{ok, chat_only: true, reply, brief}` — превью НЕ обновляется, только чат и бриф.
  - Если `action=build`: `{ok, preview_url, pages_total, pages_ok, progress}` — страницы записаны в сессию.
- **`build`** — явная генерация по кнопке (без Интервьюера): `Orchestrator::build()`. Архитектор → per-page Coder → `page:new`/`page:write` для каждой страницы. Пишет `meta.build_progress` в `context.json`.
- **`apply`** — перенос сессии в production (`SessionApplyCommand`). Блокируется, если `countBlocks() < 5` (рекурсивный счётчик по `children`).

### 8.2. Frontend

- `www/admin/aiui.php` — HTML-каркас через `Builder` (split-screen: слева iframe, справа чат).
- `www/media/damoony.ru/agent/skin/default/js/aiui.js` — логика: sendPrompt, buildSite, applyChanges.
- `www/media/damoony.ru/agent/skin/default/css/aiui.css` — тёмная тема, панель брифа, кнопка «🚀 Создать сайт».

Элементы:
- Панель брифа (📝) — живое отображение `context.input.brief`.
- Кнопка «🚀 Создать сайт по брифу» — появляется, когда бриф непустой. Вызывает `action=build`.
- Кнопка «Применить» — появляется после успешной генерации. Вызывает `action=apply`.

### 8.3. Preview

- `/admin/preview/{tab}/{alias}/{path}` → `www/admin/aipreview.php` (rewrite через `.htaccess`).
- Внутри: `FrontController::handle($request, sessionId: $tab, aliasOverride: $alias)`.
- `FileRouter` + `PageHandler` читают PHP из `app/.sess_{tab}.{domain}/{alias}/algoritm/`.
- `<base href="/admin/preview/{tab}/">` инжектится в `<head>` для корректных относительных ссылок.

### 8.4. Модели (ModelRouter)

- Выбор по роли из `config/models.config` (`ModelRouter::resolve($role)`).
- Рекомендуемый расклад:
  - `reporter` → `gemma4:e4b` (чат, брифы) — лучше говорит по-русски.
  - `architect`, `designer` → `deepseek-coder:6.7b` (JSON, план) — точнее в структуре.
- Приоритет выбора: `args['model']` → `ModelRouter` (только для `llm_provider=local`) → `agent.config.llm_model`.

### 8.5. brain.py (Wizard) — отдельный контур

- Генерация сайта «с нуля» через `bin/aicli.php` + Ollama.
- Не использует `Orchestrator` и `ModelRouter`.
- Подробнее — §6 этого документа и `_doc/install.md`.

---

## 9. Связанные документы

- `_doc/orchestrator.md` — внутренний мультиагентный конвейер AIUI (Blackboard, AgentInterface, ModelRouter, 4 action AIUI, build_progress).
- `_doc/aiui.md` — UI-документация (кнопки, панели, сессия).
- `_doc/arch.md` §4 — контракты Orchestrator и ModelRouter.
- `_doc/dev.md` — как писать страницы/блоки (CLI).
