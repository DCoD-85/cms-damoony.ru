--- НАЧАЛО ДОКУМЕНТА ---

Архитектура AI-агента (Navigator + Multi-Agent)
Дата: 1 ноября 2026. Статус: в разработке.
Связанные: ai_agent.md, architecture.md, contracts.md, cli.md, manifest.md.

1. Принцип
AI не выдумывает команды. Каждая команда возвращает next[] — контекстные
разрешённые шаги (HATEOAS). Агент выбирает из next, не угадывает.
Человек (wizard в админке) рисует кнопки из того же next[].

Navigator — единственный источник «что можно дальше».
Roles — whitelist команд по роли (defense in depth).
CLI — полный набор для человека/админки (без ролей).

2. Точки входа
- bin/cli.php — полный CLI (40 команд, включая help). Для человека, админки, отладки.
- bin/aicli.php — единственный фасад для AI. Роль + whitelist + next.
- ai_agent/ — Python orchestrator (позже): читает next, исполняет, повторяет.

3. HATEOAS-контракт aicli.php
Request (STDIN JSON):
{
  "command": "domain:new",
  "args": {"domain": "example.ru"},
  "role": "architect"
}
role — опционально, default "*" (полный whitelist).

Response:
{
  "ok": true,
  "data": {...},
  "role": "architect",
  "next": [
    {"cmd": "allias:new", "args": {"domain": "example.ru", "name": "<name>"}, "hint": "..."},
    {"cmd": "done", "args": [], "hint": "Завершить"}
  ]
}

Правила:
- next[] фильтруется по whitelist роли. done всегда доступен.
- error/hint — human-readable (для LLM).
- exit(0) при ok=true, exit(1) при ok=false.

4. Роли (Roles)
Роль = именованный whitelist команд. Файл: src/Ai/Roles.php.

role		Назначение	Команды (кратко)
parser		Разбор брифа	*:list (read-only)
inspector	Диагностика	*:list, page:read, structure:check
planner		План структуры	*:list, page:read
architect	Каркас		domain:new, allias:new, block:new, scheme:new, filler:new, *:list
executor	Контент		page:new, page:write, image:search, image:download, *:list
designer	Стили		page:write, *:list
bundler		Сборка		structure:check, *:list
corrector	Исправления	page:write, structure:repair, structure:check
reporter	Отчёт		*:list, page:read, structure:check

Все роли получают ai:start, done, engine_status автоматически.

Запрещено всем ролям: user:new, db:migrate, admin:passwd,
domain:remove, allias:remove, block:remove, scheme:remove, trash:clear,
migrate:page-rules, media:*/content:* (только через image:download).

5. Navigator (src/Ai/Navigator.php)
Граф состояний. Используется в двух местах:
- aicli.php: формирует next[] для AI
- admin UI (P2): wizard-кнопки

start(state): next[] для ai:start
  - нет доменов → domain:new
  - есть домены → allias:new, domain:new

after(cmd, args): next[] после каждой команды
  - domain:new  → allias:new, done
  - allias:new  → page:new, done
  - page:new    → page:write, done
  - page:write  → page:new, page:read, done
  - page:read   → page:write, done
  - done        → []
  - default     → []

next[] — подсказка, не enforcement. Агент может вызвать любую
команду из своего whitelist (Navigator не блокирует).

6. Multi-Agent оркестрация (ai_agent/, P2)
Секвенция ролей:
  parser → inspector → planner → [loop: architect/executor/designer/bundler] → corrector → reporter

Loop: одна задача = одна страница (1 cycle = 1 page).
После каждой страницы — bundler (сборка + structure:check).

Планировщик (Planner) — отдельный LLM-вызов: структура сайта
(список страниц, темы, titles) → JSON plan → tasks.json.

Архитектор (Architect) — в первой итерации создаёт design tokens
(общий стиль), во всех — сущности (skin, scheme, block, filler).

Исполнитель (Executor) — контент страницы (page:write + image:*).

Дизайнер (Designer) — стили, правка вёрстки.

Компоновщик (Bundler) — сборка CSS/JS, проверка (HTTP 200 + structure).

Корректор (Corrector) — исправляет failed tasks.

Сбытчик (Reporter) — финальный отчёт.

7. Контекст и задачи (ai_agent/, P2)
ai_agent/.session/<ts>/
├── brief.txt
├── config.json          {model, allowed_domains, roles_enabled}
├── state.json           {status, started_at, completed_at}
├── context.json         {домен, аллиасы, страницы, скины, медиа}
├── tasks.json           [{id, role, action, args, status, depends_on}]
├── log.txt
├── report.json
├── roles/XX_<role>/     input.json, prompt.txt, output.json, raw_response.txt
└── work/                pages/, media/, renders/

context.json — «что есть» (state).
tasks.json — «что нужно» (plan, depends_on, status).
Оркестратор: next_ready → role.run → done/fail → save.

8. Запуск (ai_agent/, P2)
Команды CLI (только человек):
  agent:new --brief=... --model=...        создать сессию
  agent:step --session=<ts>                один шаг (next_ready → role.run)
  agent:status --session=<ts>              сводка (без выполнения)
  agent:run --session=<ts>                 цикл до done/failed

aicli whitelist: agent:new, agent:step, agent:status.
agent:run — только CLI (блокирующий, для человека).

9. Что НЕ в MVP
- Headless render (визуальная проверка через Chromium) — P2
- Параллельный executor (страницы последовательно)
- Replanning (при падении planner — retry)
- Design tokens как отдельный артефакт — P2
- skin:edit/bundle, block:edit, filler:edit — P2 (нужны для bundler/designer)

10. Инварианты
- next[] — только из Navigator::start/after, фильтруется Roles.
- Роль не может вызвать команду вне своего whitelist (defense in depth).
- AI-агент не имеет доступа к user:new, db:migrate, admin:passwd,
  destructive-командам, media:*/content:*.
- aicli возвращает {ok, data, error, hint, role, next}.
- Роль "*" (default) — полный whitelist, для совместимости и человека.

11. Порядок реализации
Фаза A (готово):
- Roles, Navigator, ai:start, next[] в aicli
- *:list команды (domain, allias, page, scheme, block, filler)
- CLI --json + Result::data
- Aicli --role

Фаза B (готово):
- skin:new/list/edit/remove, scheme:save
- block:edit, filler:edit — зарегистрированы в bin/cli.php
- *:list через --json (Result::$data)

Фаза B (осталось, P2):
- skin:bundle

Фаза C (P2):
- ai_agent/ переписать под Navigator-loop (без Planner-импровизации)
- context.json + tasks.json
- agent:new/step/status/run
- 9 ролей в ai_agent/

Фаза D (P3):
- Headless render (visual check)
- Design tokens
- Parallel executor

--- КОНЕЦ ДОКУМЕНТА ---