# Рефакторинг `src/UI/` — план

Статус: **план, аудит не начат.** Живой документ; обновляется по фазам.
Связанные: `arch.md` §3.1 (модули), `dev.md` (как писать страницы), `refactor_site_config.md` (фазы проекта).

## 0. Что есть сейчас

`src/UI/` — **3 098 строк** в 17 корневых файлах и 7 подпапках:

| Часть | Строк | Что это |
|---|---:|---|
| корень (17 файлов) | ~1 498 | DSL и элементы: `Builder`, `Element`, `Attrs`, `Node`, `Text`, `Fragment`, `Slot`, `Meta`, `Document`, `Message`, `Component`, `Field` (288), `BlockCodegen` (265), `Form`, `Table`, `Button`, `UrlSanitizer` |
| `Components/` (19 файлов) | 389 | Готовые компоненты: `Box`, `Card`, `Button`, `Form`, `Input`, `Select`, `Table`, `Tbody/Td/Th/Tr/Thead`, `Label`, `Link`, `Img`, `Option`, `Textarea`, `Toggle`, `LangSwitcher` |
| `Page/` (6) | 312 | `Page`, `PageInterface`, `AdminPage`, `PageCompiler`, `PagePath`, `PageRules` |
| `Region/` (7) | 408 | `RegionContext`, `RegionInterface`, `RegionRegistry`, `RegionLoader`, `ContentRegion`, `BlockCompiler`, `FillerState` |
| `Scheme/` (3) | 186 | `SchemeCompiler`, `SchemeLoader`, `SchemeRenderer` |
| `Response/` (2) | 165 | `AjaxStruct`, `MinStruct` |
| `Url/` (3) | 100 | `UrlBuilderInterface`, `SiteUrlBuilder`, `AdminUrlBuilder` |
| `Skin/` (1) | 40 | `SkinManager` |

## 1. Найденные проблемы

### P1. Две параллельные библиотеки компонентов

- корневые `UI\Button` (50), `UI\Form` (82), `UI\Table` (52), `UI\Field` (288) — суммарно **472 строки**;
- `UI\Components\*` — **389 строк** в 19 файлах.

После удаления `app/.admin` основной потребитель `Components\*` исчез: у `Box`, `Card`,
`LangSwitcher`, `Toggle` **ноль внешних ссылок**, у остальных — только внутри `Components/` и в тестах.
При этом `BlockCodegen` (генератор кода блоков) продолжает генерировать **корневые** `Button`, `Table`, `Form`.

Итого: два набора, оба неполные, один из них фактически без потребителей.

### P2. `Field.php` — 288 строк с админским прошлым

Самый большой корневой файл. Потребитель — форма редактирования админки, которой больше нет.
Нужен точный аудит: если используется только `BlockCodegen`/тестами — удалить или свести к DSL.

### P3. Смешение обязанностей в `UI\Page`

`Page` (runtime-база страницы), `PageCompiler` (кодогенерация PHP), `PageRules` (правила роутинга),
`PagePath` (пути), `AdminPage` (админский контракт). Четыре роли в одной папке — новичок не понимает,
что от чего зависит. Кандидат на разделение, но только после P1/P2 (иначе churn).

### P4. Тесты тонкие

8 файлов на 3 098 строк:

- **покрыто:** `PageCompiler`, `BlockCompiler`, `SchemeCompiler`, `SchemeLoader`, `SchemeRenderer`,
  `SkinManager`, `RegionRegistry`, `ContentRegion`, `Builder`, `Element`, `Attrs`, `Text`, `Slot`;
- **не покрыто:** `Url\SiteUrlBuilder`, `Url\AdminUrlBuilder`, `PagePath`, `PageRules`, `FillerState`,
  `RegionLoader` (кроме session-варианта), `Document`, `Field`, `Message`.

### P5. Нет документации модуля

`_doc/ui.md` отсутствует. Знания размазаны по `dev.md` (как писать страницы),
`arch.md` (список модулей), `visuo_showcase.md` (визуальный редактор).

## 2. Фазы

### Ф0. Аудит (1 заход, без правок кода)

Для каждого класса `src/UI/**` посчитать: `use Damoony\UI\X`, `new X(`, `X::`, `extends X`,
`implements X` — **вне своего файла** и отдельно **вне тестов**. Результат — таблица
«класс → потребители (src / app / stubs / tests)» прямо в этом документе.

Выход: список кандидатов на удаление с доказательством (0 ссылок) и список «используется только тестами».

### Ф1. Удаление мёртвого

Удалить классы с нулём потребителей (кандидаты по P1: `Components/Box`, `Card`, `LangSwitcher`,
`Toggle`; по P2: `Field` — если подтвердится). Правило прежнее: удаляем в том же коммите, где гейты зелёные.

Выход: минус ~300–500 строк, ни одного класса без потребителя (кроме публичного API, см. Ф2).

### Ф2. Один набор компонентов

Решение: **что является публичным API страниц.**

Вариант A (рекомендую): публичный API — корневой DSL (`Builder`, `Element`, `Attrs`, `Text`, `Node`)
плюс `UI\Components\*`; `BlockCodegen` генерирует только DSL, корневые `Button/Form/Table` удаляются.
Вариант B: наоборот — оставить корневые, `Components` удалить целиком.

Критерий выбора: что генерирует `BlockCodegen` и что будет использовать **сборка админки** (она появится
позже и должна взять готовый набор, а не собирать третий).

### Ф3. Тесты

Добавить по одному тесту на каждую непокрытую единицу из P4 (`SiteUrlBuilder`, `AdminUrlBuilder`,
`PagePath`, `PageRules`, `FillerState`, `RegionLoader`, `Document`).

### Ф4. Документация

`_doc/ui.md`: слои (`DSL → Components → Page/Region → Scheme/Skin`), правило «новый компонент — только
в `Components/`», что генерирует `BlockCodegen`, что нельзя импортировать страницам.

### Ф5. (Опционально) Разделение `UI\Page`

`UI\Page` (runtime: `Page`, `PageInterface`, `PagePath`) и `UI\Compile` (`PageCompiler`, `PageRules`,
`BlockCompiler`?) — только если после Ф1–Ф4 папка всё ещё путает. Решение отложено.

## 3. Критерии готовности

1. У каждого класса `src/UI/**` есть потребитель вне тестов **или** он объявлен публичным API в `_doc/ui.md`.
2. Один набор компонентов; `BlockCodegen` и `Components` не дублируют друг друга.
3. Непокрытые единицы из P4 закрыты тестами.
4. `_doc/ui.md` существует и описывает слои и правило добавления компонента.
5. Гейты: тесты, phpstan, cs-fixer — зелёные после каждой фазы.
