# Руководство разработчика

Как создавать структуру сайта, писать код страниц и блоков, использовать безопасную компиляцию. Все файловые операции — через CLI (см. `cli.md`).

⚠️ **Визуальные редакторы для Страниц и Блоков удалены** (RCE). Редактирование кода — только CodeMirror в админке. Drag&Drop — только для Схем.

---

## 1. Структура и Правило 3 файлов

Любая сущность (Страница, Блок, Схема, Скин) состоит из 3 файлов:
1. `[name].php` — код.
2. `[name].rules.json` — настройки (auth, type, enabled).
3. `[name].editor.json` — данные для UI.

**Страницы** — единый префикс `algoritm_`:
- Root: `algoritm/algoritm.{php,rules.json,editor.json}`
- Вложенная: `algoritm/about/algoritm_about.{php,rules.json,editor.json}`

**Блоки / Схемы:** `[name].{php,rules.json,editor.json}` в `.alliasglobal/` или `.alliasscheme/`.

---

## 2. Написание страниц (Page)

Базовый класс — `Damoony\UI\Algoritm\Page`. Даёт:
- `$this->context` — `Meta`, `Domain`, `Request`, `service($name)`.
- `$this->regions()` — `RegionRegistry` (слоты схемы).
- `$this->setContent(string $slot, Node $node)` — наполнить слот.
- `$this->isAuthenticated()`, `$this->user()`.

### Вариант A: страница со схемой (рекомендуется)

Используйте базовый класс `PageWithScheme`. Он автоматически загружает схему (шапку/подвал) и помещает ваш контент в слот `center`.

```php
<?php

declare(strict_types=1);

namespace example_ru\www;

use Damoony\UI\Algoritm\PageWithScheme;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm extends PageWithScheme
{
    protected function content(): Node
    {
        $content = Builder::div('page-content');
        $content->append(Builder::h(1)->content('Заголовок'));
        $content->append(Builder::p()->content('Параграф'));
        
        return $content;
    }
}
```

### Вариант B: «голая» страница (API, AJAX)

Без схемы — вернуть `Node` или `Response` напрямую:

```php
final class algoritm_api extends Page
{
    public function default(): Node|Response|null
    {
        return Builder::div('api-answer')->content('ok');
    }
}
```

**Правило:** `default()` возвращает `Node`, `Response` или `null` (→ 204 No Content).

---

## 3. Создание блоков (Регионы)

Блок (Регион) — независимый элемент (шапка, футер, сайдбар). Создаётся через `block:new`.

```php
<?php

declare(strict_types=1);

namespace example_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class alliasglobal_header implements RegionInterface
{
    public function name(): string { return 'header'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('header');
        $wrap->append(Builder::h(2)->content('Логотип'));
        return $wrap;
    }
}
```

**Контракт `RegionInterface`:** `name(): string`, `enabled(): bool`, `render(RegionContext): Node`.

**Управление из страницы:** `$this->regions()->disable('header')`, `$this->setContent('center', $node)`.

**Приоритет:** `disabled` → `forcedGlobal` → `filler` → `base`.

---

## 4. Безопасная компиляция (AI & JSON)

AI **не пишет PHP**. Передаёт JSON-массив блоков через `page:write` / `block:write` / `filler:write`. Компилятор — `Damoony\UI\BlockCodegen::render()` — генерирует безопасный PHP.

**Типы блоков:** `h1`–`h6`, `text`/`p`, `img`, `link`, `button`, `ul`, `nav`. Phase 4.2 добавила `section`, `div`. Точный список — `src/UI/BlockCodegen.php`.

**Атрибуты:** `class`, `id`, `style`, `url`, `alt`, `button_type` (submit|button|reset), `items` (для `ul`), `children` (для вложенных).

**XSS-защита:**
- Текстовые узлы (`h*`, `p`, `link`, `button`, `li`): `Text::raw(strip_tags($content, '<a><strong>'))` — выживают только `<a>` и `<strong>`, остальное режется.
- URL: `UrlSanitizer::forSrc()` для `img`, `UrlSanitizer::forHref()` для `link`.

**Builder (`src/UI/Builder.php`)** — 24 метода: `el`, `elA`, `div`, `span`, `p`, `h`, `a`, `img`, `input`, `ul`, `li`, `button`, а также `nav`, `form`, `table`, `thead`, `tbody`, `tr`, `th`, `td`, `select`, `option`, `textarea`, `label`. Нет `form`/`table`/`select`/`textarea`/`label`/`nav` — обход через `Builder::el($tag)` (P2, `arch.md` §6 п.4).

**RCE:** `BlockCompiler` не поддерживает типы `custom`/`if`/`endif`. Сырой PHP в блоки через `block:write`/`filler:write` не попадает. `allow_php_blocks=false` по умолчанию.

---

## 5. Content и Skin (две разные зоны)

### 5.1. Content (`/content/`)

Runtime-контент AI (картинки). **Вне webroot**, отдаётся через `www/content.php`.

- Формат: `/content/[domain]/[allias]/[category]/[hash].[ext]` + `[hash].meta.json`.
- Запись: только CLI `content:store`.
- Чтение: URL `/content/...` для `<img src>`.
- Удаление: `content:remove` (в `/content/.trash/`).

### 5.2. Skin (`www/media/[domain]/[allias]/skin/[name]/`)

Git-tracked инфраструктура темы (CSS/JS).

- Файлы: `css/*.css`, `js/main.js`, `img/`, `skin.json`.
- Управление: `skin:new`, `skin:edit`, `skin:remove`.
- `skin:edit` разрешает файлы `css/<name>.css` (только `[a-z0-9_]` в имени) и `js/main.js`. Размер ≤ 1 MB. Блокирует `@import` и `javascript:`.

**Подключение в коде (страница или схема):**

```php
$this->context->meta->addCss('/media/example.ru/www/skin/default/css/main.css');
$this->context->meta->addJs('/media/example.ru/www/skin/default/js/main.js');
```

**Факты:** `BlockNewCommand` CSS не создаёт. `BlockCompiler` подключает `{blockName}.css` если файл существует (404 — браузер игнорирует). `brain.py` (Wizard) пишет `main.css` (base + layout + center) и `top.css` (menu). Отдельного `center.css` нет. Подробности — `arch.md` §3.4.

---

## 6. Работа с БД и Мета-информацией

```php
$this->context->meta->setTitle('Моя страница');

$db = $this->context->service('db');
if (!$db instanceof \Damoony\Data\Db\Connection) {
    return Response::internalError('DB service missing');
}
$stmt = $db->pdo()->query('SELECT * FROM news ORDER BY created_at DESC');
$news = $stmt->fetchAll();

$ul = Builder::ul('news-list');
foreach ($news as $item) {
    $ul->append(Builder::li()->content($item['title']));
}
return $ul;
```

⚠️ **DataProvider-инвариант** (`arch.md` §5): raw SQL в страницах/регионах — только для чтения через `$db->pdo()`. Запись — через `DataProvider` (`db:mutate`). Если нужна бизнес-логика — пишется в `app/[domain]/[alias]/provider/` (см. `dataprovider-design.md` §3).

---

## 7. Защита страниц (Auth)

⚠️ **Движок не форсит auth.** `.rules.json:auth` — метаданные (для admin-UI 🔒 и `structure:check`). Решение «пускать или нет» принимает сама страница.

Флаг включается через CLI:

```
php bin/cli.php page:toggle --domain=[d] --allias=[a] --name=[n] --key=auth --value=1
```

**Public-страница:**
```php
if (!$this->isAuthenticated()) {
    return Response::redirect('/login?return=' . urlencode($this->context->request->path), 302);
}
```
`isAuthenticated()` / `user()` — через `Damoony\Data\Auth\AuthInterface` (БД, `user:new`).

**Admin-страница:** наследуйте `AdminPage`:
```php
use Damoony\UI\Algoritm\AdminPage;

final class algoritm_about extends AdminPage
{
    protected function content(): Node|Response|null
    {
        // сюда попадём только если isLoggedIn() == true
    }
}
```
`AdminPage::default()` проверяет `Damoony\Admin\AuthInterface::isLoggedIn()`, при `false` → 302 на `/admin/login`.

⚠️ **Login-страница не должна наследовать `AdminPage`** (redirect loop).

Пользователи сайта создаются через `user:new` (см. `cli.md` §8).

---

## 8. Python smoke tests (`ai_agent/brain.py`)

Не-CI смоук-тесты для Python-слоя. Проверяют **чистые функции** без LLM, без Ollama, без CLI, без БД. Не входят в `composer test`.

```
python _dev/smoketests/test_brain_safety.py
```

| Файл | Назначение |
|---|---|
| `_dev/smoketests/test_brain_safety.py` | `_safe_href`, `_sanitize_name`, `validate_plan` (23 теста) |
| `_dev/smoketests/test_brain_forms.py` | auto-detect форм (Phase 4.5) |
| `_dev/smoketests/requirements.txt` | `pytest>=7.0` (опционально) |

Правила и покрытие — в самом тест-файле. Не покрыто (требует интеграции): `preflight`, `fetch_image`, `run_multi_agent` — E2E на тестовом домене.

---

## 9. Локальный сервер (Windows standalone)

Окружение и запуск — единственный источник истины: `_doc/start_new_audit.md` §4. Здесь не дублируется.

---

## 10. Multi-agent pipeline (шпаргалка)

Два независимых AI-контура для генерации контента.

### 10.1. Orchestrator (AIUI)

`src/Ai/Orchestrator/` — мультиагентный конвейер для `/admin/aiui.php`.

- **Blackboard:** `app/.sess_{tab}.{domain}/context.json` (мета, вход, `tools_context`, `agent_outputs`).
- **Агенты:** `InterviewerAgent` (`gemma4:e4b`) → `ArchitectAgent` (`deepseek-coder:6.7b`) → `CoderAgent` (`gemma4:e4b`).
- **Модели:** `ModelRouter::resolve($role)` из `config/models.config`.
- **Вход:** `POST /admin/ai_edit.php` (4 action: `start` / `preview` / `build` / `apply`).
- **Выход:** страницы в `.sess_*` (изолированы), при `apply` — в production.

Детали — `orchestrator.md`.

### 10.2. brain.py (Wizard)

`ai_agent/brain.py` — Map-Reduce, запуск «с нуля» через CLI или Web UI.

- **5 шагов:** Architect (plan) → Widgets → Content (Markdown) → main.css → top.css.
- **Вход:** `python -m ai_agent.brain <domain> <allias> [brief или @file] <model>`.
- **Детерминированные функции:** `validate_plan`, `_safe_href`, `_sanitize_name`.
- **Web UI:** `app/[domain]/agent/algoritm/ajax/algoritm_ajax.php` (4 action: chat/generate/status/cancel).

Детали — `ai.md` §6–§7.

### 10.3. Инварианты

- AI не пишет PHP — только JSON через `page:write`/`block:write`/`filler:write`.
- Сессии изолированы: `--session=X` → `.sess_*` (только `page:new`/`page:write`).
- `context.json` — единственный source of truth для Orchestrator.
- `brain.py` **не использует** Orchestrator и наоборот — разные контуры.

---

## 11. Создание таблиц через dbcli

Для новых таблиц **не пишите провайдеры руками**. Используйте декларативный подход через `bin/dbcli.php`:

```bash
# 1. Создать схему таблицы (JSON)
php bin/dbcli.php db:schema:init --domain=example.ru --table=products

# 2. Добавить колонки
php bin/dbcli.php db:schema:add-column --domain=example.ru --table=products --name=title --type=varchar --length=255
php bin/dbcli.php db:schema:add-column --domain=example.ru --table=products --name=price --type=decimal

# 3. Сгенерировать SQL и применить миграцию
php bin/dbcli.php db:schema:generate-sql --domain=example.ru
php bin/cli.php db:migrate

# 4. Сгенерировать PHP-провайдер
php bin/dbcli.php db:schema:generate-provider --domain=example.ru --table=products
```

После этого провайдер `ProductsProvider` будет доступен в `db:call` и `db:mutate`. Подробнее — `_doc/dbcli.md`.

## 12. Roadmap: реструктуризация Page/Scheme (TODO)

**Цель:** один источник истины для сборки страниц. Сейчас — дублирование `Page::default()` и `PageWithScheme::default()`, `applySkin()` дублирует `SkinCookieMiddleware`, `Slot::release()` тихо возвращает пустую строку.

### Инварианты (после реструктуризации)

1. **Схема обязательна.** Страница без схемы не работает. `Page::content(): Node` — абстрактный.
2. **Регион в слоте обязателен.** Если в схеме слот `header`, но региона `header` нет — это ошибка (severity=error в `structure:check`). `Slot::release()` в dev возвращает маркер, в prod — пустоту.
3. **`PageHandler` — единственная точка сборки.** `Page` не рендерит. `SchemeRenderer` вызывается только из `PageHandler::toResponse()`.
4. **`Page::default()` final.** Контракт: `content()` возвращает `Node`, движок сам решает SSR/AJAX.
5. **Middleware для кросс-фич.** Скин/язык/auth — Middleware, не `PageHandler`.
6. **`null` = нет данных для отдачи.** Ответ 204 No Content (не «собери схему»).

### Миграция (поэтапно, каждый шаг — зелёные тесты)

| Шаг | Что | Файлы | Риск |
|---|---|---|---|
| A | `Page::content(): ?Node` + `Page::default()` final (SSR+AJAX) | `Page.php` | средний |
| B | `PageWithScheme` -> пустой наследник `Page` (BC) | `PageWithScheme.php` | низкий |
| C | `SchemeLoader` + `SchemeRenderer` -> в `PageHandler::toResponse()` | `PageHandler.php` | средний |
| D | Удалить `PageHandler::applySkin()` (уже есть `SkinCookieMiddleware`) | `PageHandler.php` | низкий |
| E | `Slot::release()` -> маркер в dev, пусто в prod + тест | `Slot.php` | средний |
| F | Миграция 10+ админских страниц с `default()` на `content()` | `app/.admin/**` | средний |

### Правила для разработчика (после шагов A-C)

Разработчик пишет только:

    final class algoritm_about extends Page
    {
        protected function content(): Node
        {
            return Builder::div('page-content')->content('About');
        }
    }

**Больше ничего.** Никаких `SchemeLoader`, `SchemeRenderer`, `isAjax()`. Всё — в движке.


## См. также

- `cli.md` — команды `bin/cli.php`.
- `ai.md` — `bin/aicli.php`, brain.py, AINET.
- `orchestrator.md` — внутренний конвейер AIUI.
- `arch.md` §3.2 (Content vs Skin), §3.4 (CSS-факты), §5 (инварианты).