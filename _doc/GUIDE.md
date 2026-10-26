# Руководство разработчика

В этом руководстве описано, как с нуля создать сайт, добавить страницы, блоки, схемы и написать логику для них.

## 1. Создание нового сайта (домена)

Для начала нужно создать домен. Предположим, мы делаем сайт `example.ru`.

```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```

Это создаст структуру `app/alpha.0.0/domain/example.ru/` с базовым алиасом `www`.
Не забудьте добавить домен в ваш веб-сервер (Nginx/Apache) и направить его на папку `www/`.

## 2. Создание дополнительных алиасов

Алиасы нужны для разделения частей сайта (например, основной сайт и админка).

```bash
php bin/cli.php allias:new --domain=example.ru --name=admin
```

Теперь у нас есть `www` (основной сайт) и `admin` (админка).

## 3. Создание страниц

Страницы создаются в дереве `algoritm/` конкретного алиаса.

Создадим главную страницу:
```bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=index
```

Создадим страницу "О нас" внутри главной:
```bash
php bin/cli.php page:new --domain=example.ru --allias=www --parent=index --name=about
```

Это создаст файлы `algoritm_index.php` и `algoritm_index_about.php`.

## 4. Написание логики страницы

Откройте созданный файл, например `app/alpha.0.0/domain/example.ru/allias/www/algoritm/index/algoritm_index.php`.
Страница наследуется от `Algoritm` и должна реализовать метод `default()`.

```php
<?php
declare(strict_types=1);

namespace example\www;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class algoritm_index extends Algoritm
{
    public function default(): Node|Response|null
    {
        // Создаем div с классом container и текстом
        $div = Builder::div('container');
        $div->append(Builder::h(1)->content('Добро пожаловать на Example.ru'));
        $div->append(Builder::p()->content('Это главная страница.'));
        
        return $div;
    }
}
```

## 5. Создание блоков (Регионов) и Схем

Если вы хотите вынести шапку и подвал в отдельные блоки, используйте `alliasglobal`.

```bash
php bin/cli.php block:new --domain=example.ru --name=header
php bin/cli.php block:new --domain=example.ru --name=footer
```

В папке `alliasglobal` появятся классы блоков. Вы можете настроить их в файлах `.data.php`.
Затем создайте схему, которая соберет эти блоки вместе:

```bash
php bin/cli.php scheme:new --domain=example.ru --name=default
```

В файле схемы `alliasscheme/default.data.php` укажите, какие регионы использовать:

```php
<?php
return [
    'tag' => 'div',
    'class' => 'layout-default',
    'children' => [
        ['tag' => 'header', 'class' => 'header'], // вызовет блок header
        ['tag' => 'main', 'class' => 'content'],  // сюда попадет вывод страницы
        ['tag' => 'footer', 'class' => 'footer'], // вызовет блок footer
    ],
];
```

## 6. Работа с базой данных

Если вашей странице нужны данные из БД, используйте PDO-соединение (класс `Connection`).

```php
use Damoony\Db\Connection;

// Внутри метода default()
$db = new Connection(['driver' => 'sqlite', 'path' => $this->context->service('root') . '/var/damoony.db']);
$stmt = $db->pdo()->query('SELECT * FROM news ORDER BY created_at DESC');
$news = $stmt->fetchAll();

$ul = Builder::ul('news-list');
foreach ($news as $item) {
    $ul->append(Builder::li()->content($item['title']));
}
return $ul;
```

## 7. Добавление филлеров

Если блоку нужно особое поведение для конкретного алиаса, создайте filler:
```bash
php bin/cli.php filler:new --domain=example.ru --allias=admin --block=header
```
Теперь вы можете переопределить логику `header` только для админки, не меняя основной сайт.

## 8. Использование AJAX

В `Algoritm` есть метод `$this->isAjax()`. Если запрос пришел с заголовком `X-Requested-With: XMLHttpRequest`, можно вернуть JSON:

```php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}
```

## 9. Миграции

Создавайте `.sql` файлы в папке `migrations/` с префиксами `001_`, `002_` и т.д. Применяйте их командой:
```bash
php bin/cli.php db:migrate
```

## 10. Переменные и правила (.rules.json)

В `page:new` можно управлять доступом к странице через `.rules.json`.
```bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
```
Теперь страница `about` требует авторизации.