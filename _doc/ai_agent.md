--- НАЧАЛО ДОКУМЕНТА ---

Инструкция для AI-Агента (Универсальная)
Ты — AI-агент, работающий с Damoony Engine. Этот документ объединяет правила для архитектора (инфраструктура), разработчика (создание контента) и интегратора (безопасный фасад aicli.php).

Перед началом прочитай MANIFEST.md. Все P0 и P1 закрыты. Остались только задачи из P2 (техдолг).

Правило 3 файлов: Любая сущность (Страница, Блок, Схема) состоит из трех файлов: `{name}.php` (код), `{name}.rules.json` (настройки), `{name}.editor.json` (данные UI). 
Для страниц все три файла имеют единый префикс: `algoritm_{name}.{php,rules.json,editor.json}` в одной папке. Root алиаса — `algoritm/algoritm.{php,rules.json,editor.json}`. Глобальные манифесты (`blocks.json`, `schemes.json`) удалены.

---

ЧАСТЬ 1: АРХИТЕКТУРА И ИНФРАСТРУКТУРА (System)
Твоя задача: создавать домены, алиасы, схемы и управлять структурой. Ты не пишешь ядро (src/).

1.1. Главные правила
- **Только CLI.** Любая операция с файловой структурой — через `php bin/cli.php` (или через `bin/aicli.php`, если ты работаешь как AI-агент).
- **Коммит в git** перед destructive-операциями (`domain:remove`, `allias:remove`, `block:remove`, `scheme:remove`, `trash:clear --force`, `migrate:page-rules`).
- **Визуальные редакторы (Drag&Drop)** остались ТОЛЬКО для Схем. Страницы и Блоки редактируются через JSON (через AI) или CodeMirror (вручную).
- **Не пиши сырой PHP.** Ты передаёшь JSON-массив блоков через `page:write`, движок компилирует в PHP через PageCompiler (безопасно, без RCE).

1.2. Структура файлов
- Домен: папка `app/<realDomain>/`. realDomain резолвится из `site.config` (`domains_real[<host>]`).
- Алиасы (www, doc) — подпапки внутри домена.
- Инфраструктурные папки скрыты точкой: `.alliasglobal` (блоки), `.alliasscheme` (схемы).
- Страницы алиаса: `app/<domain>/<allias>/algoritm/<path>/algoritm_<path>.{php,rules.json,editor.json}`.
- Root алиаса: `app/<domain>/<allias>/algoritm/algoritm.{php,rules.json,editor.json}`.

1.3. Справочник команд (только CLI)
Домены и алиасы:
- `domain:new --domain=example.ru` — создать домен (создает www и схему, root через PageCompiler).
- `domain:remove --domain=example.ru --force` — удалить домен в карантин (16 ключей конфига).
- `allias:new --domain=example.ru --name=doc` — создать алиас (root-страница через PageCompiler).
- `allias:remove --domain=example.ru --name=doc --force` — удалить алиас в карантин (8 ключей конфига).

Блоки, схемы, филлеры:
- `block:new --domain=example.ru --name=top [--type=visual]` — создать блок (3 файла).
- `block:remove --domain=example.ru --name=top --force` — в карантин.
- `filler:new --domain=example.ru --allias=www --block=top` — filler (проверяет алиас/блок).
- `filler:toggle --domain=example.ru --allias=www --block=top --value=0|1` — idempotent, проверяет блок на диске.
- `scheme:new --domain=example.ru --name=www` — создать схему (3 файла).
- `scheme:copy --domain=example.ru --name=www` — копия (www_copy, www_copy_1, ...).
- `scheme:remove --domain=example.ru --name=www --force` — в карантин.
- `scheme:save --domain=example.ru --name=www --scheme_json='...'` — сохранить визуальную (обновить editor.json + compile).

Страницы:
- `page:new --domain=example.ru --allias=doc --name=about [--parent=docs]` — создать страницу (3 файла, родитель должен существовать).
- `page:write --domain=example.ru --allias=doc --name=about --json='[...]'` — **ГЛАВНАЯ КОМАНДА ДЛЯ AI**. Записывает контент в страницу.
  - Используй `__root__` в качестве имени (`--name='__root__'`), чтобы записать контент на главную страницу аллиаса (/).
  - `--parent` несовместим с `__root__`.
- `page:read --domain=example.ru --allias=doc --name=about` — прочитать PHP-код страницы (для проверки). Для главной — `__root__`.
- `page:toggle --domain=example.ru --allias=doc --name=about --key=auth --value=1` — переключить флаг (auth/search/default). `--path` не поддерживается (breaking change).

Диагностика:
- `structure:check` — severity-aware (error/warning, exit 0 при отсутствии errors).
- `structure:repair --domain=example.ru` — восстановить отсутствующие файлы (root через PageCompiler, editor.json, path traversal guard).
- `migrate:page-rules [--dry]` — одноразовая миграция старых `.rules.json` на новую модель. Идемпотентна.
- `trash:clear [--force] [--older=7d] [--domain=...]` — очистка карантина (4 уровня: app/, app/<domain>/, .alliasscheme/, .alliasglobal/).

Все destructive-команды (domain:remove, allias:remove, block:remove, scheme:remove, trash:clear) работают через карантин (`*_trash_<timestamp>/`) и требуют `--force`.

Path traversal guard: все команды с `--domain`/`--name` проверяют `str_contains('..')` и regex. `db:migrate --dir` ограничен подпапками `migrations/`.

---

ЧАСТЬ 2: ГЕНЕРАЦИЯ КОНТЕНТА (Logic)
⚠️ **КРИТИЧЕСКОЕ ПРАВИЛО:** Ты (AI) **НЕ ПИШЕШЬ PHP-КОД** для страниц. Ты генерируешь **JSON-массив блоков**, который движок сам безопасно компилирует в PHP через `Builder`.

2.1. Формат JSON для `page:write`
Ты должен передавать массив объектов. Поддерживаемые типы:
- `h1`, `h2`, `h3` (заголовки)
- `text` (параграф)
- `img` (картинка)
- `link` (ссылка)
- `button` (кнопка)
- `ul` (список)

Поддерживаемые атрибуты (опционально):
- `class` (CSS-классы)
- `id` (HTML id)
- `style` (inline-стили)
- `url` (для link/button)
- `alt` (для img)

**Пример идеального JSON для `page:write`:**

```json
[
  {"type": "h1", "content": "Добро пожаловать", "style": "color:#2c3e50; text-align:center;"},
  {"type": "text", "content": "Наш сайт посвящен кофе."},
  {"type": "img", "content": "https://images.unsplash.com/photo-123...", "style": "width:100%; max-width:600px;"},
  {"type": "ul", "items": ["Пункт 1", "Пункт 2"]},
  {"type": "link", "url": "/about", "content": "О нас", "style": "color:blue;"}
]
```

2.2. Шаблоны страниц (для понимания)
Если бы ты писал PHP-код (что делать не нужно), он выглядел бы так:

```php
final class algoritm_about extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');
        $content->append(Builder::h(1)->content('О нас'));
        return $content;
    }
}
```

Но ты просто передаешь `[{"type":"h1","content":"О нас"}]` в `page:write`.

2.3. Шаблон региона (.alliasglobal)
Блоки (шапки/подвалы) все еще реализуются как PHP-классы (если они не создаются через AI). Они реализуют `RegionInterface`.

```php
final class alliasglobal_top implements RegionInterface
{
    public function name(): string { return 'top'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('top');
        $wrap->append(Builder::h(2)->content('Логотип'));
        return $wrap;
    }
}
```

---

ЧАСТЬ 3: AI-ФАСАД И ИНТЕГРАЦИЯ (Agent API)
Твоя задача: работать через безопасный интерфейс `bin/aicli.php`, который связывает локальную LLM (Ollama, например Qwen 2.5 Coder) с ядром.

3.1. Архитектура (Agentic Loop)
Ты не имеешь прямого доступа к терминалу или файловой системе. Ты работаешь через безопасный цикл:

```
[AI-Модель (Qwen 2.5 Coder)]
      ↕ (JSON-план)
[Python Скрипт (Agentic Loop)]
      ↕ (JSON через STDIN)
[bin/aicli.php (Безопасный фасад)]
      ↕
[bin/cli.php (SAFE-CLI)]
```

- **Agentic Loop:** Скрипт отправляет промпт в Ollama, получает JSON-план, передает его в `aicli.php`, получает результат.
- **Разделение прав:** Ты не можешь выполнить `system('rm -rf /')`, потому что `aicli.php` принимает только разрешенные команды (Whitelist).

3.2. Whitelist (Разрешенные команды)
Список находится в `bin/aicli.php` (массив `$allowedCommands`).
Текущие разрешенные команды:
- `domain:new` (только домены из `config/agent.config`)
- `allias:new` (только для разрешенных доменов)
- `page:new` (только для разрешенных доменов)
- `page:write` (только для разрешенных доменов; принимает JSON, упаковывается в base64)
- `page:read` (только для разрешенных доменов)
- `filler_toggle` (только для разрешенных доменов)
- `engine_status`
- `read_test_file`

Запрещены (вызов вернёт `{"ok": false, "error": "Command '<name>' is not allowed."}`):
- `user:new`, `db:migrate`, `admin:passwd`, `domain:remove`, `allias:remove`, `block:new`, `block:remove`, `filler:new`, `trash:clear`, `migrate:page-rules`, `structure:repair`, `structure:check` (кроме engine_status), `scheme:*`.

3.3. Безопасность AI-слоя
- **Валидация аргументов:** Все аргументы проверяются через строгие Regex. Если ты передашь `../../etc/passwd` — скрипт вернет ошибку.
- **Whitelist доменов:** `domain` для `page:write`/`page:new`/`page:read`/`allias:new`/`filler_toggle` должен быть в `config/agent.config` (`allowed_domains`). Иначе — `Security violation: Domain 'X' is not in the allowed list.` (P0 закрыт).
- **Жесткий вызов:** Команды вызываются через `shell_exec` только с валидированными аргументами (escapeshellarg + regex).
- **JSON компиляция:** Твой JSON проходит через `PageCompiler`, который использует только безопасные методы `Builder` и `var_export` для строк.

3.4. Важные нюансы для LLM (Qwen 7B)
- **Без висячих запятых:** Валидатор Python падает на запятых перед `]` и `}`. Будь предельно внимателен при генерации JSON.
- **Без дефисов:** Имена страниц и алиасов должны содержать только `[a-z0-9_]`. Никаких дефисов (`how-to-adopt` запрещено, используй `how_to_adopt`).
- **Главная страница:** Не создавай `page:new` с именем `home` или `/`. Главная страница создается автоматически при `allias:new`. Для записи контента используй `page:write` с именем `__root__`.
- **`__root__` только для главной:** Если `--parent` передан вместе с `--name='__root__'` — ошибка.
- **Не пиши в чужой домен:** `page:write` проверяет `domain` по `allowed_domains` из `agent.config`.
- **Имена блоков/алиасов:** `[a-z][a-z0-9_]*` (начинается с буквы, не с цифры).

---

ЧАСТЬ 4: МИГРАЦИЯ PAGE-RULES
Если на диске остались старые `.rules.json` (root `algoritm/.rules.json` или `{dir}/.rules.json`), `structure:check` покажет warning:
`⚠ Устаревший /X/.rules.json — запустите migrate:page-rules`

Действия:
1. Предпросмотр: `php bin/cli.php migrate:page-rules --dry`.
2. Боевой прогон: `php bin/cli.php migrate:page-rules`.
3. Повторный `structure:check` — warning должен исчезнуть.

Шаги миграции (для понимания):
1. root: `algoritm/.rules.json` → `algoritm/algoritm.rules.json` (с union `available`).
2. подпапки: `{dir}/.rules.json` → `{dir}/algoritm_{name}.rules.json` (с union `available`).
3. нормализация полей (`available`, `auth`, `search`, `default`) для всех `algoritm_*.rules.json`.
4. создание пустых `.editor.json` для существующих `.php`.

Миграция идемпотентна — повторный запуск даёт `0/0/0`.

---

ЧАСТЬ 5: ДИАГНОСТИКА И ОЧИСТКА (CLI-аудит)
- `structure:check` — severity-aware. `✗` (error) — критично: отсутствует .php, отсутствует root algoritm.rules.json, отсутствует схема. `⚠` (warning) — некритично: отсутствует .editor.json, устаревший .rules.json, филлер без слота. Exit 0 при отсутствии errors.
- `structure:repair --domain=X` — восстанавливает отсутствующие root-страницы (через PageCompiler), rules.json/editor.json для блоков и схем. Path traversal guard.
- `trash:clear` — очистка карантина. Обходит 4 уровня: `app/<domain>_trash_<ts>/`, `app/<domain>/<allias>_trash_<ts>/`, `.alliasscheme/<name>_trash_<ts>/`, `.alliasglobal/<name>_trash_<ts>/`. Флаги: `--force` (боевой), `--older` (7d/24h/30m), `--domain` (только один домен). Dry-run по умолчанию.

---

ЧЕК-ЛИСТ ПЕРЕД СДАЧЕЙ
1. Ты сгенерировал JSON-массив, а не PHP-код.
2. В JSON нет висячих запятых.
3. Имена страниц содержат только `_` (подчеркивания), без дефисов.
4. Для записи на главную страницу использован `__root__`, без `--parent`.
5. Для инфраструктуры (создание доменов/страниц) использован CLI через `aicli.php`.
6. Domain — из `config/agent.config` (иначе будет `Security violation`).
7. Если видишь warning про устаревший `.rules.json` — сначала `migrate:page-rules`, потом продолжай.
8. Для удаления структур (если разрешено владельцем) — карантин + `--force`, не `unlink`.

ССЫЛКИ
MANIFEST.md — канон (✅/🟡/❌, P0/P1/P2).
cli.md — полный справочник команд (24 команды).
architecture.md — жизненный цикл (включая AI-слой).
contracts.md — контракты и ограничения.
guide.md — руководство разработчика (включая §15: path traversal и карантин).

--- КОНЕЦ ДОКУМЕНТА ---