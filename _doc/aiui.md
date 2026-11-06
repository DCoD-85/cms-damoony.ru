> ⚠️ **СТАТУС: ЗАМОРОЖЕНО (FROZEN).** Интеграция ИИ отложена до перестройки ядра (UI Elements / Админка / БД). Не вносить изменения без разморозки.

# AIUI (AI User Interface)

AIUI — интерфейс для редактирования страниц с помощью естественного языка (промптов). 
Пользователь не пишет PHP-код и не редактирует JSON вручную, а общается с LLM, которая перестраивает блоки страницы.

См. также: `_doc/orchestrator.md` (внутренний конвейер), `_doc/ai.md` §8 (связь с AI-слоем).

---

## 1. Архитектура

1. **Frontend (`www/admin/aiui.php`)**: Split-screen интерфейс. Слева — iframe с предпросмотром, справа — чат. Собирается через `Builder`.
2. **JS (`www/media/damoony.ru/agent/skin/default/js/aiui.js`)**: Логика чата, кнопки «Создать», отправки промптов.
3. **AJAX Endpoint (`www/admin/ai_edit.php`)**: Принимает POST с JSON `{action, domain, alias, page, tab, prompt}`.
4. **Backend (Orchestrator)**: `Orchestrator::run()` (для чата) или `Orchestrator::build()` (для генерации по кнопке).
5. **Preview (`www/admin/aipreview.php`)**: Рендерит HTML из изолированной сессии (`.sess_*`).

---

## 2. UI Элементы

1. **Чат (правая панель)**: История диалога. ИИ задает вопросы, пользователь отвечает.
2. **Панель брифа (📝)**: Появляется над полем ввода, как только ИИ накопил первые требования. Показывает краткое содержание того, что ИИ понял.
3. **Кнопка «🚀 Создать сайт по брифу»**: Появляется, когда бриф не пустой. Запускает тяжелый конвейер генерации (Architect -> Coder).
4. **Кнопка «Применить»**: Появляется в шапке превью после успешной генерации. Переносит изменения из сессии в production.

---

## 3. API Endpoint — 4 action

`POST /admin/ai_edit.php`

- **`start`**: Создать сессию (копия домена в `.sess_*`). Возвращает `{ok, tab, preview_url}`. Если сессия уже есть, она уходит в карантин и создается новая.
- **`preview`**: Интерактивный шаг (`Orchestrator::run()`).
  - Если ИИ просто общается: `{ok, chat_only: true, reply, brief}`. Превью НЕ обновляется.
  - Если ИИ сгенерировал страницу (по команде в чате): `{ok, preview_url, pages_total, pages_ok, progress}`.
- **`build`**: Явная генерация по кнопке (`Orchestrator::build()`). Возвращает прогресс по страницам.
- **`apply`**: Применить сессию к production (`SessionApplyCommand`). Заблокировано, если блоков меньше 5 (защита от пустых страниц).

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

