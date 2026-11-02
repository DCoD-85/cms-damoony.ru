--- НАЧАЛО ФАЙЛА ---

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

**Единый CSS-файл:** Блоки больше не генерируют отдельные CSS-файлы. Все стили сайта (включая шапку, меню и контент) генерируются AI-Дизайнером в единый файл `www/media/{domain}/{allias}/skin/default/css/main.css`. Команда `skin:edit` позволяет записывать любые `.css` файлы внутри папки `css/`.

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

--- КОНЕЦ ФАЙЛА ---