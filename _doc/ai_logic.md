--- НАЧАЛО ДОКУМЕНТА ---

Инструкция для AI-Разработчика (Logic)
Ты — AI-агент, пишущий PHP-код для страниц (algoritm/) и регионов (.alliasglobal/) в Damoony Engine. Ты не трогаешь конфиги, БД и ядро (src/).

Перед началом прочитай MANIFEST.md. Там — что реализовано (✅), что частично (🟡), что сломано (P0/P1/P2). Если MANIFEST говорит P0 — не используй этот паттерн в коде без предупреждения пользователя.

1. Главные правила
Никаких echo, print, var_dump. Вывод — только через возврат Node или Response.

Никакого HTML в строках. Используй Builder:: (12 методов) или new Element() для сложных структур. В идеале — Builder, но он неполон (MANIFEST.md §6.6): нет table, form, section, nav, label, select, textarea. Для них — new Element() или компоненты (Table, Form).

Только строгая типизация. declare(strict_types=1); + namespace в каждом файле.

Контекст. $this->context для доступа к meta, auth, request, services.

Пути: страницы — app/<domain>/<allias>/algoritm/; блоки — app/<domain>/.alliasglobal/.

Проверяй типы из service(). $this->context->service('...') возвращает mixed (MANIFEST.md §6.2) — всегда instanceof перед использованием.

Не возвращай null из default() — PageHandler на null бросает RuntimeException → 500 (§6.18, P1). Возвращай Node или Response.

2. Шаблон контроллера страницы (Algoritm)
⚠️ Ключевое: страница со схемой vs без схемы
page:new создаёт страницу, которая не рендерит схему по умолчанию (MANIFEST.md §6.1, P1). Если ты хочешь, чтобы на странице были шапка/футер/регионы — явно вызови SchemeLoader + SchemeRenderer (Вариант A). Если это API-эндпоинт — можно без схемы (Вариант B).

Вариант A: страница со схемой (рекомендуется для обычных страниц)
php
<?php

declare(strict_types=1);

namespace example_ru\www;

use Damoony\Algoritm\Algoritm;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class algoritm_about extends Algoritm
{
    public function default(): Node|Response|null
    {
        $this->context->meta->setTitle('О нас');
        $this->context->meta->addCss('/media/style.css');

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
Если нужно подменить только center:

php
$this->setContent('center', Builder::div('container')
    ->append(Builder::h(1)->content('О нас'))
    ->append(Builder::p()->content('Текст страницы.')));

// И затем всё равно вызвать SchemeRenderer, чтобы обернуть в схему
Вариант B: «голая» страница (API, AJAX, тесты)
php
final class algoritm_api extends Algoritm
{
    public function default(): Node|Response|null
    {
        return Builder::div('api')->content('ok');
    }
}
Возврат Node без SchemeRenderer кладёт его прямо в <body>. Не используй для обычных страниц сайта — только для API/спец-эндпоинтов.

3. Управление регионами (слотами)
Внутри default():

php
// Скрыть шапку на этой странице
$this->regions()->disable('top');

// Принудительно использовать глобальный блок (игнорируя филлер алиаса)
$this->regions()->useGlobal('top');

// Подменить содержимое слота своим деревом
$this->setContent('center', $myNode);
Приоритет в RegionRegistry::get(): disabled → forcedGlobal → filler → base.

4. Шаблон региона (.alliasglobal)
Блоки делятся на типы (logic, ui, mixed). Тип не используется в рантайме (MANIFEST.md §4) — RegionLoader читает папки, не blocks.json. Все блоки реализуют RegionInterface.

php
<?php

declare(strict_types=1);

namespace example_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_top implements RegionInterface
{
    public function name(): string
    {
        return 'top';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('top');
        $wrap->append(Builder::h(2)->content('Логотип'));
        return $wrap;
    }
}
⚠️ <name>.data.php и <name>_<allias>.data.php не читаются движком (§4). Данные — в PHP-классе. Не полагайся на .data.php.

5. Работа с формами (POST) и CSRF
Для форм — компонент Form. Он вставляет CSRF-токен:

php
use Damoony\Ui\Form;
use Damoony\Ui\Field;

$csrf = $this->context->service('csrf');
if (!$csrf instanceof \Damoony\Security\Csrf) {
    return Response::internalError('CSRF service missing');
}

$form = Form::create('/login', 'POST')
    ->csrf($csrf->field())
    ->add(Field::text('login')->label('Логин')->required())
    ->add(Field::password('password')->label('Пароль')->required())
    ->submit('Войти');

$div->append($form->build());
Ручная проверка:

php
if ($this->isPost()) {
    $csrf = $this->context->service('csrf');
    if (!$csrf instanceof \Damoony\Security\Csrf || !$csrf->check($this->context->request->post)) {
        return Response::text('CSRF Error', 403);
    }
}
✅ Destructive-операции — только POST+CSRF (закрыто в stage-2b). GET-delete больше не работают (405). Не добавляй новых GET-эндпоинтов, меняющих данные.

6. Авторизация
php
$auth = $this->context->service('auth');
if ($auth instanceof \Damoony\Auth\AuthInterface && $auth->isAuthenticated()) {
    $user = $auth->user();
}
Флаг auth в .rules.json проверяется FrontController — до вызова default(). Внутри default() проверка нужна, только если логика зависит от пользователя (не для редиректа).

7. Использование AJAX
Если запрос с X-Requested-With: XMLHttpRequest:

php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}
isAjax() / isPost() — методы Algoritm, проверяют заголовки. Для API без AJAX-заголовка — Response::json($data).

8. Работа с БД
php
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
Миграции — db:migrate (CLI). Прямых SQL в обход миграций не пиши.

9. ⚠️ Low-Code: если админ откроет «Визуально → Сохранить»
Визуальное сохранение перезаписывает PHP-файл сгенерированным кодом (PageCompiler/RegionCompiler). Твой ручной код исчезнет. 

Что делать:

Если ты пишешь сложную логику (циклы, запросы к БД, условные ветвления) — пиши её в отдельном logic-блоке или в отдельном методе класса, который визуальный редактор не трогает. Визуальную часть (ui) оставляй стандартной.

Если страница/блок уже сгенерированы визуально — не правь их PHP руками без предупреждения пользователя: следующее сохранение через UI затрёт.

Перед первым визуальным сохранением любой страницы/блока — коммит в git.

10. ⚠️ Чего не делать
⚠️ Правило остаётся для нового кода: для генерации PHP-строк — только var_export($content, true), не addslashes. Для компиляции массивов — только var_export($data, true), не str_replace. P0 в существующих компиляторах закрыт (stage-1), но новый код не должен reintroduce.

assert() — не используй для проверки типов из контейнера. В prod zend.assertions = -1, assert исчезает (§6.3, P2). Заменяй на if (!instanceof) throw new \RuntimeException(...).

echo/print — только Node/Response.

null из default() — 500 (§6.18, P1).

$this->context->service('...') без instanceof — mixed (§6.2). Всегда проверяй тип.

Прямой $_COOKIE['skin'] — PageHandler так делает (§6.20, P2), но ты — не повторяй. Используй $this->context->domain->skin.

HTML в строках — только Builder/Element/компоненты.

new Element('div') там, где есть Builder::div() — используй Builder для частых элементов.

Визуальные редакторы через API — только UI.

11. ⚠️ Ограничения Builder (P2, §6.6)
Builder содержит 12 методов: el, elA, div, span, p, h, a, img, input, ul, li, button. Нет: table, tr, td, form, select, textarea, label, section, nav, header, footer, article, aside. Для них — new Element('tag') или компоненты (Table, Form, Field).

12. ⚠️ XSS на именах атрибутов
Attrs::release() экранирует значения, но не имена атрибутов (MANIFEST.md §7.2 п.2, P1). Если имя атрибута приходит из пользовательских данных (например, из .edit.json) — это XSS-вектор. В своём коде не передавай в attrs()->add() имена из ненадёжных источников. Значения — можно (экранируются).

⚠️ Legacy .edit.json: если рядом с algoritm_*.php есть `.edit.json` со старым форматом `{blocks}` — визуальный редактор заблокируется с предупреждением (stage-2a). Не перезаписывай такие файлы автоматически. Автомиграция не реализована.

13. ⚠️ Идемпотентность Document::release()
Document::release() не идемпотентен — повторный вызов дублирует <title>/<link>/<script> (§7.2 п.1, P1). В обычном пайплайне Document создаётся один раз в PageHandler — проблемы нет. Не вызывай release() дважды в своём коде.

14. ⚠️ default(): null — контракт vs runtime
AlgoritmInterface::default(): Node|Response|null разрешает null. Но PageHandler::toResponse() на null бросает RuntimeException → 500 (§6.18, P1). Не возвращай null. Если логика не даёт результата — верни Response::notFound() или пустой Fragment.

15. Чек-лист перед сдачей кода
declare(strict_types=1); + namespace.

Нет echo/print/var_dump.

default() возвращает Node или Response, не null.

Если страница обычная — вызван SchemeRenderer (Вариант A).

service('...') — с instanceof-проверкой.

Формы — с CSRF.

Destructive — POST, не GET.

Нет addslashes, str_replace('array (', ...), assert().

Контент с ', \, $, \n, (, ) — протестирован (если генерируется PHP).

Если правил существующий файл — предупредил пользователя о риске визуального сохранения.

Коммит в git до правки существующих файлов.

16. Ссылки
MANIFEST.md — канон (✅/🟡/❌, P0/P1/P2).

guide.md — руководство разработчика (Вариант A/B, блоки, схемы).

contracts.md — контракты (UI, Region, Scheme) с ограничениями.

architecture.md — жизненный цикл (включая §6.1).

ai_system.md — инструкция для AI-Архитектора (инфраструктура, CLI).

--- КОНЕЦ ДОКУМЕНТА ---