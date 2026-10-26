--- НАЧАЛО ФАЙЛА---

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

Откройте созданный файл, например `app/.../allias/www/algoritm/index/algoritm_index.php`.
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

В папке `alliasglobal` появятся классы блоков. Реализуйте их в файлах `.php` (интерфейс `RegionInterface`).
Затем создайте схему, которая соберет эти блоки вместе:

```bash
php bin/cli.php scheme:new --domain=example.ru --name=default
```

В файле схемы `alliasscheme/default.data.php` укажите, в какие слоты вставлять регионы:

```php
<?php
return [
    'tag' => 'div',
    'class' => 'layout-default',
    'children' => [
        ['tag' => 'header', 'slot' => 'header'], // вызовет регион header
        ['tag' => 'main', 'slot' => 'content'],  // сюда попадет вывод страницы (если подмените через setContent)
        ['tag' => 'footer', 'slot' => 'footer'], // вызовет регион footer
    ],
];
```

## 6. Работа с базой данных

Если вашей странице нужны данные из БД, используйте PDO-соединение через контекст.

```php
use Damoony\Db\Connection;

// Внутри метода default()
$db = $this->context->service('db'); // (или получите из DI контейнера)
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

## 8. Управление регионами из кода

Внутри `Algoritm` у вас есть доступ к `RegionRegistry`. Вы можете управлять блоками прямо из логики страницы:

```php
// Отключить шапку на этой странице
$this->regions()->disable('header');

// Принудительно использовать глобальный блок (игнорируя филлер)
$this->regions()->useGlobal('header');

// Подменить целый регион своим деревом
$myContent = Builder::div('custom')->content('Привет');
$this->setContent('content', $myContent);
```

## 9. Мета-информация (CSS, JS, Title)

Для управления содержимым тега `<head>` используйте объект `Meta` из контекста:

```php
// Установить заголовок страницы
$this->context->meta->setTitle('Моя страница');

// Подключить CSS и JS
$this->context->meta->addCss('/media/example.ru/skin/default/css/style.css');
$this->context->meta->addJs('/media/example.ru/skin/default/js/app.js');
```
*(Они автоматически попадут в `<head>` при рендеринге).*

## 10. Авторизация (Login/Logout)

Чтобы страница требовала входа, добавьте `"auth": true` в её `.rules.json`:
```json
{
    "available": ["about"],
    "auth": true
}
```
Движок автоматически перенаправит неавторизованного пользователя на `/login`.

Создайте пользователя для входа:
```bash
php bin/cli.php user:new --login=admin --email=admin@example.com --password=secret
```

Внутри логики страницы вы можете получить текущего пользователя:
```php
$auth = $this->context->service('auth');
if ($auth->isAuthenticated()) {
    $user = $auth->user();
    // ...
}
```

## 11. Использование AJAX

В `Algoritm` есть метод `$this->isAjax()`. Если запрос пришел с заголовком `X-Requested-With: XMLHttpRequest`, можно вернуть JSON:

```php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}
```

## 12. Миграции

Создавайте `.sql` файлы в папке `migrations/` с префиксами `001_`, `002_` и т.д. Применяйте их командой:
```bash
php bin/cli.php db:migrate
```

## 13. Переменные и правила (.rules.json)

В `page:toggle` можно управлять доступом к странице через `.rules.json`.
```bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
```
Теперь страница `about` требует авторизации.

--- КОНЕЦ ФАЙЛА ---