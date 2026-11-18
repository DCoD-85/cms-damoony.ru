# UI: страницы, схемы, блоки

## Минимальная страница

`app/example.ru/www/algoritm/lang/about/algoritm_lang_about.php`:

```php
<?php

declare(strict_types=1);

namespace example_ru\www;

use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Page\Page;

final class algoritm_lang_about extends Page
{
    protected function content(): Node
    {
        return Builder::div('about')
            ->append(Builder::h(1)->content('О проекте'))
            ->append(Builder::p()->content('Текст страницы'));
    }
}
```

Рядом:

`algoritm_lang_about.rules.json`:

```json
{
    "available": { "": "^$" },
    "auth": false,
    "csrf": true,
    "search": true
}
```

`algoritm_lang_about.editor.json` — метаданные редактора (может быть `[]`).

URL: `https://example.ru/ru/about/` (язык — первый сегмент).

## Правила

| Ключ | Значение |
|---|---|
| `available` | regex по сегментам пути |
| `auth` | требовать авторизацию |
| `csrf` | проверять CSRF для POST |
| `search` | индексировать страницу |

Для форм с собственным CSRF-токеном ставьте `"csrf": false` и проверяйте токен в коде.

## Схема

`app/example.ru/.schemesglobal/www.php`:

```php
<?php

$children = [
    ['tag' => 'header', 'class' => 'site_top', 'slot' => 'top'],
    ['tag' => 'main', 'class' => 'site_center', 'slot' => 'center'],
    ['tag' => 'footer', 'class' => 'site_footer', 'slot' => 'footer'],
];

return [
    'tag' => 'div',
    'class' => 'site-shell',
    'children' => $children,
    'meta' => [
        'css' => ['/media/shared/css/elements.css'],
        'js' => [],
    ],
];
```

Слоты `top/left/center/right/bottom/footer` заполняются блоками.

## Блок

`app/example.ru/www/.blockslocal/left/left.php`:

```php
<?php

declare(strict_types=1);

namespace example_ru;

use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class blockslocal_www_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        return Builder::ul('menu')
            ->append(Builder::li()->append(Builder::a('/' . $context->domain->language . '/')->content('Главная')));
    }
}
```

Имя класса: `blockslocal_{alias}_{name}`; глобальные — `blocksglobal_{name}`.

## Builder

```php
Builder::div('card');
Builder::h(1)->content('Заголовок');
Builder::p()->content('Текст');
Builder::a('/ru/about/')->content('Ссылка');
Builder::ul('menu');
Builder::li()->append(Builder::a('/')->content('Главная'));
Builder::button('submit', 'btn')->content('Сохранить');
```

`content()` принимает строку или `Node`; строки экранируются.

## AJAX

- страница может вернуть `Response::redirect(...)` — AJAX вернёт `form.redirect`;
- блоки обновляются по `Page::ajaxBlocks()`;
- `cliajax.js` перехватывает ссылки и POST-формы;
- для отключения: `data-no-ajax` на форме/ссылке.

## Скины

CSS/JS лежат в `www/media/{folder}/{alias}/skin/{name}/`:

```
skin/default/skin.json
skin/default/css/main.css
skin/default/js/main.js
```

Схема подключает skin через `<link>`; смена скина — `PageHandler`/skin cookie.
