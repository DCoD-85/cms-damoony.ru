--- НАЧАЛО ФАЙЛА: _doc/AI_LOGIC.md ---

# Инструкция для AI-Разработчика (Logic)

Ты — AI-агент, пишущий PHP-код для страниц и регионов в Damoony Engine.
Твоя задача: создавать контроллеры (в папках `algoritm/`) и блоки (в скрытой папке `.alliasglobal/`). Ты не трогаешь конфиги и базу данных.

## 1. Главные правила
- **Никаких `echo`, `print`, `var_dump`.** Вывод только через возврат объекта `Node` (или `Response`).
- **Никакого HTML в строках.** Используй статические методы `Builder::` (например, `Builder::div()`, `Builder::a()`).
- **Используй UI-компоненты для сложных элементов.** Вместо ручной сборки форм и таблиц, используй готовые классы из `Damoony\Ui\` (`Form`, `Field`, `Table`, `Button`, `Message`).
- **Только строгая типизация.** В каждом файле `declare(strict_types=1);` и namespace.
- **Контекст.** Всегда используй `$this->context` для доступа к `meta`, `auth`, `request`.
- **Пути:** Страницы лежат в `app/<domain>/<allias>/algoritm/`. Блоки — в `app/<domain>/.alliasglobal/`.

## 2. Шаблон контроллера страницы (Algoritm)
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
        // Добавляем CSS и Title в <head>
        $this->context->meta->setTitle('Главная');
        $this->context->meta->addCss('/media/style.css');

        // Строим дерево
        $div = Builder::div('wrapper');
        $div->append(Builder::h(1)->content('Привет'));
        $div->append(Builder::p()->content('Мир'));

        return $div; // Отдаем в слот center
    }
}
```

## 3. Управление регионами (слотами)
Внутри `default()` ты можешь управлять блоками:
- `$this->regions()->disable('top');` — скрыть шапку на этой странице.
- `$this->regions()->useGlobal('top');` — показать базовую шапку (игнорируя филлер алиаса).
- `$this->setContent('center', $myNode);` — подменить центр своим контентом.

## 4. Шаблон региона (.alliasglobal)
```php
<?php
declare(strict_types=1);

namespace example;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

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

## 5. Работа с формами (POST) и CSRF
Для форм используй компонент `Form`. Он автоматически вставляет CSRF-токен и защищает от подделки запросов.
```php
use Damoony\Ui\Form;
use Damoony\Ui\Field;

// Создаем форму
$form = Form::create('/login', 'POST')
    ->csrf($this->context->service('csrf')->field())
    ->add(Field::text('login')->label('Логин')->required())
    ->add(Field::password('password')->label('Пароль')->required())
    ->submit('Войти');

// Добавляем на страницу
$div->append($form->build());
```
Если форма обрабатывается вручную, проверяй токен:
```php
if ($this->isPost()) {
    $csrf = $this->context->service('csrf');
    if (!$csrf->check($this->context->request->post)) {
        return Response::text('CSRF Error', 403);
    }
}
```

## 6. Авторизация
Узнать, вошел ли пользователь:
```php
$auth = $this->context->service('auth');
if ($auth->isAuthenticated()) {
    $user = $auth->user();
}
```

## 7. Использование AJAX
Если запрос пришел с заголовком `X-Requested-With: XMLHttpRequest`, можно вернуть JSON-структуру (`MinStruct` или `AjaxStruct`):
```php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}
```

--- КОНЕЦ ФАЙЛА: _doc/AI_LOGIC.md ---