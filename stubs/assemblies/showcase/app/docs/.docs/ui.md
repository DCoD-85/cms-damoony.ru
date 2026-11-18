[🏠 В начало](../../../../../README.md) · [📚 Документация](../../../../../_doc/README.md) · [UI](../../../../../_doc/ui.md)

# UI: элементы

Правило: **элемент — одна обёртка с простым API**, внутренняя разметка скрыта.
Сборка не собирает внутренности (`Table → Tbody → Tr → Td` — явная структура, без «данных в конструктор»).

## Контракт классов

| Класс | Компонент | Что стилизует |
|---|---|---|
| `ui-btn` | `Components\Button` | кнопка |
| `ui-input` | `Components\Input` | поле ввода |
| `ui-textarea` | `Components\Textarea` | многострочное поле |
| `ui-select` | `Components\Select` | выпадающий список |
| `ui-label` | `Components\Label` | подпись поля |
| `ui-form` | `Components\Form` | форма |
| `ui-form-wrapper` | `Components\Form` | обёртка формы |
| `ui-table` | `Components\Table` | таблица |
| `ui-thead`, `ui-tbody`, `ui-tr`, `ui-th`, `ui-td` | `Tbody/Thead/Tr/Th/Td` | структура таблицы |
| `ui-link` | `Components\Link` | ссылка |
| `ui-img` | `Components\Img` | изображение |
| `ui-error` | список ошибок формы | ошибки валидации |

Стили: `www/media/shared/css/elements.css` (общие для всех скинов). Скин задаёт только переменные
темы: `--accent`, `--accent-weak`, `--border`, `--panel`, `--text`, `--muted`, `--radius`.

## Правила вставки

`Element::append()` проверяет `accepts()`:

- `Table` → `Thead`/`Tbody`/`Tr`; `Tbody`/`Thead` → `Tr`; `Tr` → `Td`/`Th`;
- `Td`/`Th` → любой узел/строка, **кроме** `Table`, `Tbody`, `Thead`, `Tr`, `Td`, `Th`;
- `Select` → `Option`; `Button` → только текст; `Input` → ничего (void);
- `Form`: дети только через `add()`; нельзя вкладывать `Form` и `Table`.

Ошибка: `InvalidArgumentException` с текстом «В `<td>` нельзя вставлять `<td>`».

## Пример

```php
$row = new Tr();
$row->append(new Td('Анна'));
$row->append(new Td('admin'));
$row->append(new Td(new Button('Изменить', 'button')));

$tbody = new Tbody();
$tbody->append($row);

$table = new Table();
$table->append($tbody);
```
