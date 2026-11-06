# Руководство разработчика

Этот документ описывает, как создавать структуру сайта, писать код страниц и блоков, а также работать с безопасной компиляцией. Все файловые операции выполняются через CLI (см. `CLI.md`).

⚠️ **Важно:** Визуальные редакторы для Страниц и Блоков удалены в целях безопасности (защита от RCE). Всё редактирование кода происходит через CodeMirror в админке. Визуальный Drag&Drop оставлен ТОЛЬКО для Схем.

---

## 1. Структура и Правило 3 файлов

Любая сущность (Страница, Блок, Схема, Скин) состоит из трёх файлов:
1. `[name].php` — код.
2. `[name].rules.json` — настройки (auth, type, enabled).
3. `[name].editor.json` — данные для UI.

**Для страниц** все три файла имеют единый префикс `algoritm_`:
- Root: `algoritm/algoritm.{php,rules.json,editor.json}`
- Вложенная: `algoritm/about/algoritm_about.{php,rules.json,editor.json}`

---

## 2. Написание страниц (Page)

Базовый класс `Page` предоставляет методы `$this->context` (Meta, Domain), `$this->regions()` и `$this->isAuthenticated()`.

### Вариант A: страница со схемой (рекомендуется)
Чтобы страница имела шапку, подвал и другие блоки, её нужно обернуть в Схему.

```php
<?php

declare(strict_types=1);

namespace example_ru\www;

use Damoony\Algoritm\Page;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Node;

final class algoritm_about extends Page
{
    public function default(): Node|Response|null
    {
        $this->context->meta->setTitle('О нас');

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::internalError('Paths service missing');
        }

        $schemeLoader = new SchemeLoader($paths);
        $manifest = $schemeLoader->load($this->context->domain, $this->context->domain->scheme);

        $renderer = new SchemeRenderer();

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}
```

### Вариант B: «голая» страница (API, AJAX)
Если шапка и футер не нужны, возвращайте `Node` напрямую (или `Response`).

```php
final class algoritm_api extends Page
{
    public function default(): Node|Response|null
    {
        return Builder::div('api-answer')->content('ok');
    }
}
```

---

## 3. Создание блоков (Регионы)

Блок (Регион) — это независимый элемент (шапка, футер, сайдбар). Он должен реализовывать `RegionInterface`. Создаётся через `block:new`.

```php
<?php

declare(strict_types=1);

namespace example_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

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
*Управление из кода:* `$this->regions()->disable('header')` или `$this->setContent('center', $node)`.
*Приоритет:* `disabled` -> `forcedGlobal` -> `filler` -> `base`.

---

## 4. Безопасная компиляция (AI & JSON)

Для AI-агентов и автоматизации используется **PageCompiler** и **BlockCompiler**. AI не пишет PHP-код. Он передаёт JSON-массив блоков через `page:write` (или `block:write`, `filler:write`).
Поддерживаемые типы: `h1`, `h2`, `h3`, `text`, `img`, `link`, `button`, `ul`, `nav`. 
Атрибуты: `class`, `id`, `style`, `url`, `alt`.

**Тип `nav`:** Генерирует список ссылок `<ul class="menu"><li><a href="/...">...</a></li></ul>`. Ожидает `items` в виде `[{"title": "...", "url": "..."}]`. Используется для меню.

Компиляторы используют только безопасные методы `Builder` и `var_export`, что исключает выполнение вредоносного кода (RCE).

---

## 5. Content и Skin (Медиа и Стили)

Это две разные зоны. Не смешивайте их!

### 5.1. Content (`/content/`)
Runtime-хранилище AI-контента (картинки, скачанные из интернета). **Вне webroot.**
- Запись: только через CLI `content:store`.
- Получение пути (local_url): `/content/[domain]/[allias]/[category]/[hash].[ext]`.
- В коде страницы используйте этот URL для тега `<img>`.

### 5.2. Skin (`www/media/[domain]/[allias]/skin/`)
Git-tracked инфраструктура (CSS/JS темы). Управление — через `skin:new` / `skin:edit`.
В коде страницы (или в схеме) подключайте их через `Meta`:
```php
$this->context->meta->addCss('/media/example.ru/www/skin/default/css/main.css');
$this->context->meta->addJs('/media/example.ru/www/skin/default/js/main.js');
```

**Единый CSS-файл:** `BlockNewCommand` CSS-файлов не создаёт. `BlockCompiler` подключает `{blockName}.css` по имени блока, если файл существует (404 — браузер игнорирует, не падает). `brain.py` (Wizard) пишет два файла: `main.css` (base + layout + center) и `top.css` (меню). См. `arch.md` §3.4 (единственный источник истины). `skin:edit` позволяет записывать любые `.css` внутри папки `css/`.

---

## 6. Работа с БД и Мета-информацией

```php
// Установка заголовка
$this->context->meta->setTitle('Моя страница');

// Работа с БД через PDO
$db = $this->context->service('db');
if (!$db instanceof \Damoony\Db\Connection) {
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

---

## 7. Защита страниц (Auth)

Чтобы страница требовала вход, переключите флаг через CLI:
`php bin/cli.php page:toggle --domain=[d] --allias=[a] --name=[n] --key=auth --value=1`

В коде проверка:
```php
if ($this->isAuthenticated()) {
    $user = $this->user();
}
```

---

## 8. Python smoke tests (`ai_agent/brain.py`)

Не-CI смоук-тесты для Python-слоя. Проверяют **чистые функции** без LLM, без Ollama, без CLI, без БД. Не входят в `composer test` (там только PHPUnit).

Запускать вручную: перед E2E `python -m ai_agent.brain ...` и после любой правки `ai_agent/brain.py`.

### 8.1. Расположение

| Файл | Назначение |
|---|---|
| `_dev/smoketests/test_brain_safety.py` | 23 теста, встроенный плейн-раннер |
| `_dev/smoketests/requirements.txt` | `pytest>=7.0` (опционально) |

### 8.2. Запуск без зависимостей (рекомендуется)

    python _dev/smoketests/test_brain_safety.py

Ожидаемый вывод:

      ✓ test_safe_href_anchor
      ✓ test_safe_href_data_rejected
      ...
      ✓ test_validate_plan_topo_sort

    23 passed, 0 failed

Работает на любом Python 3.10+ без `pip install`.

### 8.3. Запуск с pytest (опционально)

Сначала убедись, что `python` и `pytest` — один интерпретатор:

    python -c "import sys; print(sys.executable)"
    python -m pytest --version

Если `No module named pytest` — два Python (MSYS2 + Windows). Не смешивай. Варианты:

    # A. Поставить в тот python, что в PATH (MSYS2)
    python -m pip install --user pytest

    # B. Использовать Windows python
    py -3 -m pip install --user pytest
    py -3 -m pytest _dev/smoketests/ -v

    # C. Плейн-раннер (без pytest) — полностью покрывает
    python _dev/smoketests/test_brain_safety.py

### 8.4. Покрытие

**`_safe_href(url)`** — валидация URL перед `<a href>`:

| Кейс | Ожидание |
|---|---|
| `/about`, `#anchor`, `https://...`, `http://...` | без изменений |
| `javascript:`, `data:`, `vbscript:`, `file:`, `//evil` | `None` |
| `/foo" onclick="x` | `/foo%22 onclick=%22x` |
| `/foo<script>` | `/foo%3Cscript%3E` |

Закрывает XSS через `[LINK: ...]`: `PageCompiler`/`BlockCompiler` используют `Text::raw(strip_tags($s, '<a><strong>'))` — тег `<a>` выживает, но href санитизирован на стороне Python.

**`_sanitize_name(name)`** — санитизация имени страницы:

| Вход | Выход |
|---|---|
| `About` / `About Us` / `Care-Guide!` | `about` / `aboutus` / `careguide` |
| `__root__` | `__root__` |
| `123page` | `page_123page` |
| `''` | `page` |

Закрывает path traversal в имени до CLI.

**`validate_plan(plan)`** — детерминированная правка плана LLM:

- topo sort (parent перед child)
- drop orphan parent → `None`
- split склеенных имён: `care_guide_documentation` → `documentation`
- sanitize parent: `Parent With Spaces` → orphan → `None`
- cycle guard (A↔B не вешает)

### 8.5. Что не покрыто (требует интеграции)

- `preflight` — нужен `bin/aicli.php` + `config/agent.config`
- `fetch_image` — нужен AINET + whitelist
- `run_multi_agent` — полный пайплайн с Ollama

Для этого — E2E на тестовом домене:

    python -m ai_agent.brain damoony3test.ru www "Тестовый бриф" qwen2.5-coder:7b


---

## 9. Локальный сервер (Windows standalone)

Проект больше не использует OSPanel. Вместо него развернут автономный стек **Nginx + PHP-CGI + MariaDB** в папке `E:\\localserver`. 
Это решает проблему падения `proc_open` (из-за модуля Apache `mpm_winnt`) и дает среду, максимально похожую на Debian.

### 9.1. Структура
- `E:\\localserver\\domains\\damoony3.ru\\` — корень проекта (git-репозиторий).
- `E:\\localserver\\tools\\` — бинарники сервера (`nginx`, `php`, `mariadb`).
- `E:\\localserver\\server.ps1` — скрипт управления (трей-иконка).
- `E:\\localserver\\server.vbs` — лаунчер (запускает скрипт без окна консоли).

### 9.2. Управление
- **Трей-иконка:** в системном трее висит иконка (правый клик — Запустить/Остановить/Перезапустить, открыть AIUI).
- **Автозагрузка:** сервер стартует автоматически при входе в Windows.
- **Логи:** `E:\\localserver\\logs\\` (ошибки Nginx и access-логи).

### 9.3. Запуск CLI-команд (Важно!)
Системный `php` в терминале (GitBash/CMD) может указывать на старую версию без нужных расширений (например, без `curl`). 
**Всегда запускайте CLI через наш бинарник с явным указанием конфига:**

    "/e/localserver/tools/php/php.exe" -c "/e/localserver/tools/php/php.ini" bin/cli.php [команда]

*Пример:*

    "/e/localserver/tools/php/php.exe" -c "/e/localserver/tools/php/php.ini" bin/cli.php structure:check

Для удобства можно создать алиас в GitBash (в `~/.bashrc`):

    alias php='/e/localserver/tools/php/php.exe'
    alias nginx='/e/localserver/tools/nginx/nginx.exe'
