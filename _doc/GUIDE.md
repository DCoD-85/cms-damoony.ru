--- НАЧАЛО ФАЙЛА ---

# Руководство разработчика

В этом руководстве описано, как с нуля создать сайт, добавить страницы, блоки, схемы и написать логику для них. Разработку можно вести как через командную строку (CLI), так и через веб-интерфейс (Админку).

## 1. Концепция и структура
Архитектура разделена на два уровня:
- **Домен (Инфраструктура):** Папка `app/<domain>/`. Здесь хранятся настройки, привязки хостов и глобальные ресурсы.
- **Алиас (Контент):** Папки `app/<domain>/<allias>/` (например, `www`, `doc`). Здесь живут конкретные страницы.

Инфраструктурные папки домена скрыты точкой:
- `.alliasglobal/` — глобальные блоки (регионы).
- `.alliasscheme/` — глобальные схемы (каркасы).

## 2. Создание сайта (Домена)
**Через CLI:**
```bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
```
**Через Админку:** Раздел «Домены» -> «+ Добавить домен».

## 3. Создание дополнительных алиасов
**Через CLI:**
```bash
php bin/cli.php allias:new --domain=example.ru --name=admin
```
**Через Админку:** Настройки домена -> вкладка «Алиасы» -> «+ Создать алиас».

## 4. Создание страниц
**Через CLI:**
```bash
php bin/cli.php page:new --domain=example.ru --allias=www --parent=index --name=about
```
**Через Админку:** Дашборд алиаса (`www`) -> вкладка «Страницы» -> «+ Создать страницу».
Это создаст файлы `algoritm_index.php` и `algoritm_index_about.php` в папке `app/example.ru/www/algoritm/`.

## 5. Написание логики страницы
Страница наследуется от `Algoritm` и должна реализовать метод `default()`. Вывод — только через возврат объекта `Node` (без `echo` и `print`).

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
        // Мета-информация
        $this->context->meta->setTitle('Главная');
        
        // Построение дерева
        $div = Builder::div('container');
        $div->append(Builder::h(1)->content('Добро пожаловать на Example.ru'));
        $div->append(Builder::p()->content('Это главная страница.'));
        
        return $div;
    }
}
```
*Совет:* Вы можете редактировать PHP-код прямо из браузера, нажав «Редактировать» в списке страниц админки.

## 6. Создание блоков (Регионов) и Схем
Блоки выносятся в `.alliasglobal`.
```bash
php bin/cli.php block:new --domain=example.ru --name=header
```
В файле блока (интерфейс `RegionInterface`) описывается логика рендеринга шапки.
Затем создается глобальная схема, которая собирает блоки:
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
        ['tag' => 'header', 'slot' => 'header'], // вызовет регион header
        ['tag' => 'main', 'slot' => 'content'],  // сюда попадет вывод страницы
        ['tag' => 'footer', 'slot' => 'footer'], // вызовет регион footer
    ],
];
```

## 7. Управление регионами из кода (Runtime API)
Внутри `default()` вы можете управлять регионами на лету:
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
*(Ресурсы автоматически подключатся при рендеринге).*

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
Если запрос пришел с заголовком `X-Requested-With`, можно вернуть JSON:
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
Это создаст `header_admin.php` и автоматически включит его в `site.config`. Управлять филлерами можно в админке (вкладка «Блоки» внутри алиаса).

--- КОНЕЦ ФАЙЛА ---