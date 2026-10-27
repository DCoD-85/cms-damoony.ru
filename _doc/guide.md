--- НАЧАЛО ФАЙЛА _docrebuild/guide.md ---
# Руководство разработчика

В этом руководстве описано, как с нуля создать сайт, добавить страницы, блоки, схемы и написать логику для них. Разработку можно вести как через командную строку (CLI), так и через веб-интерфейс (Админку).

## 1. Концепция и структура

Архитектура разделена на два уровня:

- **Домен (Инфраструктура):** папка `app/<domain>/`. Здесь хранятся настройки, привязки хостов и глобальные ресурсы.
- **Алиас (Контент):** папки `app/<domain>/<allias>/` (например, `www`, `doc`). Здесь живут конкретные страницы.

Инфраструктурные папки домена скрыты точкой:

- `.alliasglobal/` — глобальные блоки (регионы).
- `.alliasscheme/` — глобальные схемы (каркасы).

## 2. Создание сайта (Домена)

Через CLI:

```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

Через Админку: раздел «Домены» → «+ Добавить домен».

**Алиас `www` создаётся автоматически** — отдельный `allias:new` для него не нужен.

## 3. Создание дополнительных алиасов

Через CLI:

```bash
php bin/cli.php allias:new --domain=example.ru --name=admin
```

Через Админку: Настройки домена → вкладка «Алиасы» → «+ Создать алиас».

## 4. Создание страниц

Через CLI:

```bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
```

Через Админку: Дашборд алиаса (`www`) → вкладка «Страницы» → «+ Создать страницу».

Это создаст **папку** `about/` с контроллером и `.rules.json`, а также добавит `"about"` в `available` файла `algoritm/.rules.json`:

```
app/example.ru/www/algoritm/
├── .rules.json                  (available += ["about"])
├── algoritm.php
└── about/
    ├── .rules.json              ({"available": ["about"]})
    └── algoritm_about.php       (класс algoritm_about)
```

Вложенная страница создаётся через `--parent`:

```bash
php bin/cli.php page:new --domain=example.ru --allias=www --parent=about --name=team
```

Даст:

```
algoritm/about/team/
├── .rules.json
└── algoritm_about_team.php      (класс algoritm_about_team)
```

## 5. Написание логики страницы

Страница наследуется от `Algoritm` и должна реализовать метод `default()`. Вывод — только через возврат объекта `Node` (без `echo` и `print`). Namespace генерируется автоматически: `<домен>\<allias>` (например, `example_ru\www`).

```php
<?php

declare(strict_types=1);

namespace example_ru\www;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class algoritm_about extends Algoritm
{
    public function default(): Node|Response|null
    {
        // Мета-информация
        $this->context->meta->setTitle('О нас');

        // Построение дерева
        $div = Builder::div('container');
        $div->append(Builder::h(1)->content('О нас'));
        $div->append(Builder::p()->content('Текст страницы.'));

        return $div;
    }
}
```

Совет: PHP-код можно редактировать прямо из браузера — кнопка «Редактировать» в списке страниц админки.

## 6. Создание блоков (Регионов) и Схем

Блоки выносятся в `.alliasglobal/`.

```bash
php bin/cli.php block:new --domain=example.ru --name=header
```

Создаст:

```
app/example.ru/.alliasglobal/header/
├── header.php       (класс alliasglobal_header implements RegionInterface)
└── header.data.php
```

Сгенерированный `header.php` уже содержит namespace домена и реализацию `RegionInterface` (`name() / enabled() / render()`). Дальше достаточно отредактировать `render()`.

Затем создаётся глобальная схема, которая собирает блоки:

```bash
php bin/cli.php scheme:new --domain=example.ru --name=default
```

В файле `app/example.ru/.alliasscheme/default.data.php` указываются слоты:

```php
<?php

return [
    'tag' => 'div',
    'class' => 'layout-default',
    'children' => [
        ['tag' => 'header', 'slot' => 'header'],
        ['tag' => 'main', 'slot' => 'content'],
        ['tag' => 'footer', 'slot' => 'footer'],
    ],
];
```

## 7. Управление регионами из кода (Runtime API)

Внутри `default()` можно управлять регионами на лету:

```php
// Отключить шапку на этой конкретной странице
$this->regions()->disable('header');

// Принудительно использовать глобальный блок (игнорируя филлер алиаса)
$this->regions()->useGlobal('header');

// Подменить целый регион своим деревом
$myContent = Builder::div('custom')->content('Привет');
$this->setContent('content', $myContent);
```

## 8. Мета-информация (CSS, JS, Title)

Управление `<head>` через объект `Meta` из контекста:

```php
$this->context->meta->setTitle('Страница');
$this->context->meta->addCss('/media/example.ru/skin/default/css/style.css');
$this->context->meta->addJs('/media/example.ru/skin/default/js/app.js');
```

Ресурсы автоматически подключатся при рендеринге.

## 9. Работа с базой данных

Используйте PDO-соединение через контекст.

```php
$db = $this->context->service('db');
$stmt = $db->pdo()->query('SELECT * FROM news ORDER BY created_at DESC');
$news = $stmt->fetchAll();

$ul = Builder::ul('news-list');
foreach ($news as $item) {
    $ul->append(Builder::li()->content($item['title']));
}
return $ul;
```

## 10. Авторизация и Безопасность

Чтобы страница требовала вход, добавьте `"auth": true` в её `.rules.json`:

```json
{
    "available": ["about"],
    "auth": true
}
```

Или через CLI:

```bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
```

Создать пользователя:

```bash
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
```

Проверка авторизации в коде:

```php
$auth = $this->context->service('auth');
if ($auth->isAuthenticated()) {
    $user = $auth->user();
}
```

## 11. Использование AJAX

Если запрос пришёл с заголовком `X-Requested-With`, можно вернуть JSON:

```php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}
```

## 12. Миграции

Файлы `.sql` кладутся в `migrations/` (с префиксами `001_`, `002_`). Применяются командой:

```bash
php bin/cli.php db:migrate
```

## 13. Филлеры (переопределение блоков)

Чтобы изменить шапку только для одного алиаса:

```bash
php bin/cli.php filler:new --domain=example.ru --allias=admin --block=header
```

Это создаст файлы `header_admin.php` и `header_admin.data.php` **в папке блока** `app/example.ru/.alliasglobal/header/` и автоматически включит филлер в `site.config`.

```
app/example.ru/.alliasglobal/header/
├── header.php
├── header.data.php
├── header_admin.php        (класс alliasglobal_header_admin implements RegionInterface)
└── header_admin.data.php
```

Включить/выключить филлер:

```bash
php bin/cli.php filler:toggle --domain=example.ru --allias=admin --block=header --value=0
```

Управлять филлерами можно и в админке: вкладка «Блоки» внутри алиаса.
--- КОНЕЦ ФАЙЛА _docrebuild/guide.md ---