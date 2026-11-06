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

## 2. Роли

9 ролей + `*` (all). Полный whitelist — `src/Ai/Roles.php`.

| Роль | Назначение |
|---|---|
| `parser` | Разбор брифа (`*:list` только) |
| `inspector` | Диагностика + `db:schema/methods/describe/call` |
| `planner` | План структуры (read-only) |
| `architect` | Каркас: `domain:new`, `allias:new`, `block:new`, `scheme:new`, `filler:new`, `dataprovider:add-method` |
| `executor` | Контент + БД: `page:new`, `page:write`, `db:call`, `db:mutate --force` |
| `designer` | Стили + филлеры: `skin:edit`, `block:write`, `filler:write` |
| `bundler` | Сборка: `structure:check` |
| `corrector` | Исправления: `page:write`, `structure:repair` |
| `reporter` | Отчёт: `*:list`, `structure:check`, `db:call` |

Запрещено всем: `user:new`, `db:migrate`, `admin:passwd`, destructive-команды, `CREATE/DROP/ALTER TABLE`. `DataProvider` — только через `bin/aicli.php`/`brain.py`, не через Orchestrator/AIUI.

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
- `fetch` — GET-запрос к API (`--url="..." --type=json|text`). **Единственная команда AINET без префикса `net:`** (исторически: реализована в `bin/ainet.php` до введения префиксов). В `Roles.php` whitelist — `'net:status', 'image:search', 'image:download', 'fetch'`. При расширении AINET — не переименовывать без обратной совместимости.

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

## 8.4. ModelRouter


- Выбор по роли из `config/models.config` (`ModelRouter::resolve($role)`).
- Рекомендуемый расклад:
  - `reporter` → `gemma4:e4b` (чат, брифы) — лучше говорит по-русски.
  - `architect`, `designer` → `deepseek-coder:6.7b` (JSON, план) — точнее в структуре.
- Приоритет выбора: `args['model']` → `ModelRouter` (только для `llm_provider=local`) → `agent.config.llm_model`.

## 8.5. brain.py vs Orchestrator


- Генерация сайта «с нуля» через `bin/aicli.php` + Ollama.
- Не использует `Orchestrator` и `ModelRouter`.
- Подробнее — §6 этого документа и `_doc/install.md`.

---

---

## 9. Связанные документы

- `_doc/orchestrator.md` — внутренний мультиагентный конвейер AIUI (Blackboard, AgentInterface, ModelRouter, 4 action AIUI, build_progress).
- `_doc/aiui.md` — UI-документация (кнопки, панели, сессия).
- `_doc/arch.md` §4 — контракты Orchestrator и ModelRouter.
- `_doc/dev.md` — как писать страницы/блоки (CLI).
