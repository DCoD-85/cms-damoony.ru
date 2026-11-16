> ⚠️ **СТАТУС: ЗАМОРОЖЕНО (FROZEN).** Интеграция ИИ отложена до перестройки ядра (UI Elements / Админка / БД). Не вносить изменения без разморозки.

> ℹ️ **ФАКТИЧЕСКОЕ СОСТОЯНИЕ (2026-11-16):** HTTP-путь AIUI **не реализован**. Файлов `www/admin/aiui.php`, `www/admin/ai_edit.php`, `www/admin/aipreview.php`, `www/media/damoony.ru/agent/skin/default/js/aiui.js` нет в git. Реально существует только CLI-команда `ai:edit-page` (`src/Cli/Commands/AiEditPageCommand.php`), которая пишет в production напрямую, без `--session` и без `countBlocks`-защиты. Backend `Orchestrator` (`run()`/`build()`) существует, но HTTP-обёртки к нему нет.

# AIUI (AI User Interface)

AIUI — интерфейс для редактирования страниц с помощью естественного языка (промптов). 
Пользователь не пишет PHP-код и не редактирует JSON вручную, а общается с LLM, которая перестраивает блоки страницы.

См. также: `_doc/orchestrator.md` (внутренний конвейер), `_doc/ai.md` §8 (связь с AI-слоем).

---

## 1. Архитектура (фактическое состояние)

**HTTP-путь AIUI не реализован.** Следующих файлов нет в git:
- `www/admin/aiui.php` — frontend (split-screen с чатом и превью)
- `www/admin/ai_edit.php` — AJAX endpoint (4 action)
- `www/admin/aipreview.php` — preview-рендерер из `.sess_*`
- `www/media/damoony.ru/agent/skin/default/js/aiui.js` — клиентский JS

**Что реально существует:**

1. **CLI (`ai:edit-page`)**: `src/Cli/Commands/AiEditPageCommand.php`. Читает `editor.json` страницы, формирует LLM-промпт, вызывает `AgentChatHandler::handle('agent:chat', ..., 'designer')`, парсит JSON, при `--force=1` пишет `editor.json` и вызывает `page:write`. **Пишет в production напрямую, без `--session` и без `countBlocks`-защиты.**
2. **Backend (Orchestrator)**: `src/Ai/Orchestrator/Orchestrator.php` содержит `run()` (стр. 28) и `build()` (стр. 121) — но они не имеют HTTP-обёртки.
3. **Session-инфраструктура**: `session:start/apply/discard/list` (`bin/cli.php`), `PageHandler` читает из `.sess_*` (`src/Core/App/PageHandler.php:126–127`). Готова к использованию, но HTTP-путь AIUI её не использует.

---

## 2. UI Элементы

1. **Чат (правая панель)**: История диалога. ИИ задает вопросы, пользователь отвечает.
2. **Панель брифа (📝)**: Появляется над полем ввода, как только ИИ накопил первые требования. Показывает краткое содержание того, что ИИ понял.
3. **Кнопка «🚀 Создать сайт по брифу»**: Появляется, когда бриф не пустой. Запускает тяжелый конвейер генерации (Architect -> Coder).
4. **Кнопка «Применить»**: Появляется в шапке превью после успешной генерации. Переносит изменения из сессии в production.

---

## 3. API Endpoint — НЕ РЕАЛИЗОВАН

`POST /admin/ai_edit.php` — **файл не существует в git**. 4 action (`start`/`preview`/`build`/`apply`), описанные ниже, — дизайн без реализации.

Если/когда endpoint будет создан, предполагаемые контракты (на существующих классах):
- **`start`**: `SessionStartCommand` (`bin/cli.php`) — **существует**.
- **`preview`**: `Orchestrator::run()` — метод **существует** (`Orchestrator.php:28`).
- **`build`**: `Orchestrator::build()` — метод **существует** (`Orchestrator.php:121`).
- **`apply`**: `SessionApplyCommand` — **существует**; `countBlocks < 5` защита — в нём, не в `ai:edit-page`.

**Пример ответа `action=preview` (chat_only):**

    {
      "ok": true,
      "chat_only": true,
      "reply": "Отлично! Какой стиль предпочитаете?",
      "brief": "Сайт кофейни"
    }

**Пример ответа `action=build`:**

    {
      "ok": true,
      "preview_url": "/admin/preview/{tab}/",
      "pages_total": 5,
      "pages_ok": 4,
      "progress": [
        {"page": "home", "status": "done", "blocks": 12},
        {"page": "menu", "status": "failed", "error": "..."}
      ],
      "status": "build_partial"
    }

---

## 4. Изоляция (Sessions)

- При `action=start` создается копия домена: `app/.sess_{tab}.{domain}/` и `www/media/.sess_{tab}.{domain}/`.
- Все команды записи (`page:new`, `page:write`) выполняются с флагом `--session={tab}`. Они физически не могут изменить production.
- Превью рендерится из `app/.sess_{tab}.{domain}/www/algoritm/`.
- При нажатии «Применить» папка сессии перемещается в production (старая уходит в `_trash_`).

---

## 5. E2E Сценарий

1. Пользователь пишет «нужен сайт кофейни» → ИИ накапливает бриф.
2. Кнопка «Создать сайт» → Architect + Coder → страницы в сессии.
3. Превью обновляется → «Применить» → production.

Детали стадий — `_doc/orchestrator.md` §6-§7.

## 6. Безопасность и ограничения

**Гарантировано:**
- Никакого raw PHP (JSON → `BlockCodegen` → `Builder`).
- Сессии изолированы (`--session=X` → `.sess_*`).
- `apply` блокируется при `countBlocks() < 5`.
- Лимиты 600с (`php.ini`, `nginx.conf`, `AgentChatHandler`).

**Ограничения** (см. также `arch.md` §6):
- Стили не генерируются (`DesignerAgent` отсутствует) — skin по умолчанию, или `skin:new`/`skin:edit`, или Wizard (`brain.py`).
- Нет Undo/rollback — только `git` или `session:discard`.
- `build_partial` возможен (страницы со `status: failed` в `meta.build_progress`).
- `PageNewCommand --session` на Windows требует рестарт PHP-CGI (opcache).

**Рекомендации:** проверять бриф перед «Создать», смотреть `meta.build_progress`, стили — отдельным шагом.

