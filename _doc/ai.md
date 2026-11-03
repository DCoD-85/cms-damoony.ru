--- НАЧАЛО ФАЙЛА ---

# AI-Слой: Интеграция, AINET и Мультиагентная генерация

Этот документ описывает, как локальные LLM (например, Ollama) взаимодействуют с движком. Единственная точка входа для ИИ-команд — `bin/aicli.php`. AINET (сеть) изолирован от файловой системы. Для полной автономной сборки сайтов используется Python-оркестратор `ai_agent/brain.py`.

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
5. **Modular Designer:** LLM анализирует классы и генерирует модульные CSS-файлы: `main.css` (переменные), `top.css` (меню), `center.css` (контент). Базовый layout (сетка, ограничение картинок) зашит в Python-код.


**Заменить на:**
```markdown
**Запуск:**
```bash
python -m ai_agent.brain <domain> <allias> "<brief|@file>" <model_name> [--session=ID]
```

--- КОНЕЦ ФАЙЛА ---