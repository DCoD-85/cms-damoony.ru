# AI-Слой: Интеграция, AINET и Оркестраторы

Этот документ описывает, как локальные LLM (например, через Ollama) взаимодействуют с движком. Единственная точка входа для ИИ — `bin/aicli.php`. AINET (сеть) изолирован от файловой системы.

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
| `designer` | Стили | `page:write`, `block:edit`, `filler:edit`, `*:list` |
| `bundler` | Сборка | `structure:check`, `*:list` |
| `corrector` | Исправления | `page:write`, `structure:repair`, `structure:check` |
| `reporter` | Отчёт | `*:list`, `page:read`, `structure:check` |

**Роль `*` (по умолчанию):** объединяет все 9 ролей и добавляет специфичные команды (`net:*`, `skin:remove`, `filler_toggle`).
**Запрещено всем:** `user:new`, `db:migrate`, `admin:passwd`, а также любые destructive-команды (`*:remove`, `trash:clear`, `content:remove`).

---

## 3. Navigator (Граф состояний)

Скрипт `src/Ai/Navigator.php` формирует массив `next[]` на основе текущего состояния.
- `OFFERABLE` = `[domain:new, allias:new, skin:new, skin:edit, page:new, page:write, page:read, image:search, image:download, done]`.
- `next[]` фильтруется по whitelist роли (если роль `executor`, она не увидит `domain:new`).

**Порядок шагов (Architect -> Executor):**
1. `ai:start` -> `domain:new`
2. `domain:new` -> `allias:new`
3. `allias:new` -> `page:new` (или `skin:new`)
4. `page:new` -> `page:write` (или `image:search`, если нужны картинки)
5. `image:search` -> `image:download` (скачать выбранную картинку)
6. `image:download` -> `page:write` (вставить `local_url`)
7. `page:write` -> `page:new` (следующая страница) или `done`

---

## 4. Безопасная генерация контента (PageCompiler)

⚠️ **AI НЕ ПИШЕТ PHP-КОД.** Он передаёт JSON-массив блоков через `page:write`. PageCompiler конвертирует его в безопасный PHP.

**Поддерживаемые типы:** `h1`, `h2`, `h3`, `text`, `img`, `link`, `button`, `ul`.
**Атрибуты:** `class`, `id`, `style`, `url`, `alt`.

*Правила:*
- JSON должен быть валидным (без висячих запятых).
- Имена страниц/алиасов: `[a-z][a-z0-9_]*` (без дефисов).
- Для главной страницы: `--name=__root__`. Флаг `--parent` с `__root__` несовместим.

---

## 5. AINET (Выход в интернет)

Библиотека `src/Ainet/` позволяет AI искать картинки и получать данные. **AINET не пишет на диск** — он передаёт байты в CLI (`content:store`).

### Команды:
- `net:status` — диагностика (провайдеры, лимиты, кэш).
- `image:search` — поиск картинок (`--query="..." --count=5`). Провайдеры: `wikimedia` (default), `openverse` (fallback).
- `image:download` — скачивание картинки в `/content/`. Аргументы: `url`, `domain`, `allias`, `category`, `alt`. Возвращает `local_url`.
- `fetch` — GET-запрос к API (`--url="..." --type=json|text`).

### Безопасность (SSRF):
- Только GET-запросы.
- Whitelist URL-доменов в `config/ainet.config` (без wildcard). Независим от `agent.config`.
- Блокировка private IP (`127.0.0.1`, `10.0.0.0/8`, AWS metadata `169.254.169.254` и т.д.).
- Ручные редиректы (`CURLOPT_FOLLOWLOCATION=false`), повторный UrlGuard на каждый Location.
- Rate limit: 30 req/min. Кэш: 7 дней.

---

## 6. Настройка локальных оркестраторов

Для автономной генерации сайтов используются Python-оркестраторы.

1. **`agent_test.py`** (рабочий E2E-скрипт).
   - Настраивается на локальную модель через Ollama (например, Qwen 2.5 Coder 7B).
   - Читает бриф, генерирует JSON-план (цепочку команд), отправляет в `aicli.php`, обрабатывает ошибки, делает commit (шаг).
   - Умеет искать и скачивать картинки (`image:search` -> `image:download`), вставлять `local_url` в `page:write`.

2. **`ai_agent/`** (Будущая multi-agent модель, P3).
   - Прототип архитектуры Planner -> Builder -> Content.
   - В данный момент не используется в production, но является референсом для перехода на полноценную мультиагентную оркестрацию (с Transaction Rollback и батчами).

---

## 7. Чек-лист для AI (перед отправкой команды)

1. Ты передаешь JSON-массив (не PHP-код).
2. Нет висячих запятых в JSON.
3. Имена без дефисов и пробелов.
4. Для главной страницы используется `__root__` (без `--parent`).
5. Домен находится в `config/agent.config` (иначе `Security violation`).
6. URL картинки находится в `config/ainet.config` (иначе `Domain not in whitelist`).
7. Если `ok: false` — прочитай `hint`, исправь ошибку и попробуй снова.