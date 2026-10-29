--- НАЧАЛО ДОКУМЕНТА ---

Руководство разработчика
В этом руководстве описано, как с нуля создать сайт, добавить страницы, блоки, схемы и написать логику. Разработка ведётся двумя путями: через Low-Code (визуальный редактор, для контент-менеджеров) или через PHP-код (для разработчиков). Перед началом прочитайте MANIFEST.md — там зафиксировано, что реализовано, что частично, а что ещё нет.

⚠️ Важно перед стартом. Визуальный редактор затирает ручной PHP-код без предупреждения. Если страница написана руками, не открывайте её в «Визуально → Сохранить». Обратное тоже верно: если страница сгенерирована визуально, правки в PHP будут потеряны при следующем сохранении через UI. Legacy-формат {blocks} блокируется (stage-2a).

1. Концепция и структура
Архитектура разделена на два уровня:

Домен (Инфраструктура): папка app/<domain>/. Настройки, привязки хостов, глобальные ресурсы.

Алиас (Контент): папка app/<domain>/<allias>/ (например, www, doc). Конкретные страницы.

Инфраструктурные папки домена скрыты точкой:

.alliasglobal/ — глобальные блоки (регионы);

.alliasscheme/ — глобальные схемы (каркасы).

Термины — по MANIFEST.md §2. Не используйте «модуль», «плагин», «виджет», «шаблон».

2. Создание сайта (Домена)
Через CLI:

bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
Через Админку: раздел «Домены» → «+ Добавить домен».

Алиас www создаётся автоматически. Отдельный allias:new для него не нужен.

Что создаётся:

text
app/example.ru/
├── .alliasglobal/
├── .alliasscheme/
└── www/
    └── algoritm/
        ├── .rules.json
        └── algoritm.php
После создания добавьте домен в config/site.config (CLI делает это автоматически). Для работы по IP или нестандартному порту — см. MANIFEST.md §6.17 (порт в Host нормализуется с stage-3).

3. Создание дополнительных алиасов
bash
php bin/cli.php allias:new --domain=example.ru --name=doc
Через Админку: Настройки домена → вкладка «Алиасы» → «+ Создать алиас».

Что создаётся:

text
app/example.ru/doc/algoritm/
├── .rules.json
└── algoritm.php
На один домен — сколько угодно алиасов. Но на один IP-хост без DNS работает только один алиас (www), потому что алиас определяется по Host, а не по пути. Для нескольких алиасов нужны разные хосты (DNS или hosts-файл).

4. Создание страниц и логики
Создание через CLI
bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
Создаётся папка about/ с контроллером и .rules.json:

text
app/example.ru/www/algoritm/
├── .rules.json                  (available += ["about"])
├── algoritm.php
└── about/
    ├── .rules.json              ({"available": ["about"]})
    └── algoritm_about.php       (класс algoritm_about)
⚠️ Ключевое: страница без схемы vs со схемой
page:new создаёт страницу, которая не рендерит схему по умолчанию. Это означает: шапки, футера и других регионов на такой странице не будет, только возвращённый Node. Это отличается от страниц, сохранённых через визуальный редактор (там PageCompiler вставляет SchemeLoader). Разница — скрытая, см. MANIFEST.md §6.1.

У вас есть два варианта ручной страницы.

Вариант A: страница со схемой (рекомендуется)
Наследуйтесь от HomePage (он уже вызывает SchemeLoader + SchemeRenderer) или сами вызовите рендерер:

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
Если хотите только подменить center, не трогая остальные слоты:

php
$this->setContent('center', Builder::div('container')
    ->append(Builder::h(1)->content('О нас'))
    ->append(Builder::p()->content('Текст страницы.')));
Вариант B: «голая» страница без схемы
Возврат Node без вызова SchemeRenderer кладёт его в <body> как есть — без шапки, футера, регионов:

php
final class algoritm_api extends Algoritm
{
    public function default(): Node|Response|null
    {
        return Builder::div('api-answer')->content('ok');
    }
}
Это осознанный режим для API-эндпоинтов, ajax-ответов и подобного. Но не для обычных страниц сайта. Если вы не уверены — используйте Вариант A.

⚠️ default(): null ломает страницу
AlgoritmInterface разрешает null, но PageHandler на null бросает RuntimeException → 500. Не возвращайте null из default(). См. MANIFEST.md §6.18.

Ручное редактирование кода
В админке — кнопка «Редактировать» (CodeMirror). Напрямую в файле — обычным редактором. Namespace: <домен_с_подчёркиваниями>\<allias>. Класс — algoritm_<путь_с_подчёркиваниями>. Вывод — только через Node или Response. Никаких echo, print, var_dump.

⚠️ Визуальное сохранение
Если файл уже существует и вы нажимаете «Визуально → Сохранить», PageCompiler перезапишет файл сгенерированным кодом. Ручные правки исчезнут — это нормальный контракт визуального редактора. Перед первым визуальным сохранением — коммит в git. Legacy-формат `{blocks}` (старый .edit.json) блокируется: `edit()` покажет предупреждение, а `save()` не перезапишет PHP (stage-2a).

5. Создание блоков (Регионов)
bash
php bin/cli.php block:new --domain=example.ru --name=header --type=mixed
Типы блоков:

visual — только вёрстка (статичный HTML);

script — только скрипт (возвращает данные, не участвует в визуализации);

mixed — реализация (данные + вёрстка).

Что создаётся:

text
app/example.ru/.alliasglobal/header/
├── header.php       (класс alliasglobal_header implements RegionInterface)
└── header.data.php
⚠️ .data.php блоков не читается движком (см. MANIFEST.md §4). Файл создаётся CLI, но RegionLoader его игнорирует. Если данные нужны — используйте $context->service('db') или статический массив прямо в header.php.

Сгенерированный header.php содержит namespace домена и реализацию RegionInterface:

php
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
В админке — визуальный редактор (ui/mixed) или CodeMirror (logic/mixed). Визуальный редактор блоков работает и не теряет данные, но при сохранении перезаписывает header.php. Не правьте header.php руками, если блок редактируется визуально.

6. Создание Схем (Каркасов)
Визуальный редактор полностью переработан. Работает на Drag&Drop. Разделяет блоки на зоны: Неактивные, Скрипты (только script), Визуал (visual и mixed). Поддерживает вложенность (в visual можно класть другие блоки).

7. Управление регионами из кода (Runtime API)
Внутри default():

php
// Отключить шапку на этой конкретной странице
$this->regions()->disable('header');

// Принудительно использовать глобальный блок (игнорируя филлер алиаса)
$this->regions()->useGlobal('header');

// Подменить содержимое слота своим деревом
$this->setContent('center', $myNode);
setContent регистрирует ContentRegion с именем слота. Порядок приоритетов в RegionRegistry::get(): disabled → forcedGlobal → filler → base.

8. Мета-информация (CSS, JS, Title)
php
$this->context->meta->setTitle('Страница');
$this->context->meta->addCss('/media/example.ru/skin/default/css/style.css');
$this->context->meta->addJs('/media/example.ru/skin/default/js/app.js');
$this->context->meta->addMeta('description', 'Описание страницы');
⚠️ Meta::render() подставляется в <head> через Text::raw() в Document::release(). Не вызывайте Document::release() дважды — <title>/<link>/<script> удвоятся (MANIFEST.md §7.2 п.1). В обычном HTTP-пайплайне Document создаётся один раз в PageHandler — этой проблемы не будет. Если пишете свою обёртку — не делайте $doc->release() повторно.

9. Работа с базой данных
PDO-соединение — через контекст:

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
service('db') возвращает mixed (MANIFEST.md §6.2). Всегда проверяйте instanceof на стороне вызывающего.

Миграции — только через CLI: php bin/cli.php db:migrate. Прямых SQL-запросов в обход миграций не пишите.

10. Авторизация и Безопасность
Чтобы страница требовала вход, добавьте "auth": true в .rules.json или через page:toggle:

bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --path=about --key=auth --value=1
В коде:

php
$auth = $this->context->service('auth');
if ($auth instanceof \Damoony\Auth\AuthInterface && $auth->isAuthenticated()) {
    $user = $auth->user();
}
Пользователи сайта (для страниц с auth: true) создаются через user:new или в админке. Админка использует отдельную авторизацию (config/admin.config), независимую от пользователей сайта. Не смешивайте.

Все POST — с CSRF. Для форм используйте компонент Form::create(...)->csrf($this->context->service('csrf')->field()). Ручная проверка — $csrf->check($request->post) → при неудаче Response::text('CSRF Error', 403).

11. Использование AJAX
Если запрос с X-Requested-With: XMLHttpRequest — можно вернуть JSON:

php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}
isAjax() и isPost() — методы базового Algoritm, проверяют заголовки Request. MinStruct — минимальная структура (error, messages, release, form), AjaxStruct — с layers (слои dt_top, dt_center и т.д.). Для API-эндпоинтов без AJAX-заголовка — возвращайте Response::json($data) и не забудьте exit-защиту от Document (если вернули Response — PageHandler не оборачивает в Document).

12. Филлеры (переопределение блоков)
Чтобы изменить шапку только для одного алиаса:

bash
php bin/cli.php filler:new --domain=example.ru --allias=admin --block=header
Создаются header_admin.php и header_admin.data.php в папке блока. Филлер включается автоматически (fillers[domain][allias][block] = true в site.config). В админке — кнопка «Синхронизировать», создающая филлеры сразу для всех глобальных блоков.

Управление:

bash
# Выключить филлер (будет использоваться глобальный блок)
php bin/cli.php filler:toggle --domain=example.ru --allias=admin --block=header --value=0
⚠️ header_admin.data.php не читается движком (та же проблема, что с .data.php блоков, MANIFEST.md §4). Данные — в header_admin.php.

13. Диагностика
bash
php bin/cli.php structure:check
Проверяет: наличие .rules.json и algoritm.php, схем, глобальных блоков, стыковку филлер↔слот. Не проверяет: .edit.json ↔ PHP, legacy .edit.json, blocks.json ↔ папки, schemes.json ↔ файлы. Это P1 (MANIFEST.md §7.2 п.3). Для полной картины — смотрите админку «Обзор системы» и вручную проверяйте расхождения.

14. Чек-лист перед деплоем страницы
Схема: страница рендерит схему (Вариант A) или это осознанный API-эндпоинт (Вариант B)?

Визуал: если страница ручная — не открывайте её в визуальном редакторе. Если визуальная — не правьте PHP руками.

✅ .edit.json: если рядом есть .edit.json старого формата {blocks} — визуальный редактор покажет предупреждение и не откроется. Удалите `.edit.json` или перепишите в формат `{slots}` вручную (stage-2a).

default(): не возвращает null.

✅ POST: с CSRF. Destructive: через POST+CSRF (закрыто в stage-2b).

structure:check: зелёный.

git: коммит до первого визуального сохранения.

Подробнее о состоянии проекта — MANIFEST.md. О командах — cli.md. Об админке — admin.md. О жизненном цикле запроса — architecture.md.

--- КОНЕЦ ДОКУМЕНТА ---