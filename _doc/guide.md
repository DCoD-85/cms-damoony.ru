--- НАЧАЛО ДОКУМЕНТА ---

Руководство разработчика
В этом руководстве описано, как с нуля создать сайт, добавить страницы, блоки, схемы и написать логику. Разработка ведётся двумя путями: через CLI (инфраструктура) или через PHP-код (для разработчиков). Перед началом прочитайте MANIFEST.md — там зафиксировано, что реализовано, а что нет.

⚠️ Важно перед стартом. Визуальные редакторы для Страниц и Блоков удалены в целях безопасности (предотвращение RCE). Всё редактирование кода происходит через встроенный CodeMirror. Визуальный Drag&Drop оставлен ТОЛЬКО для Схем.

1. Концепция и структура
Архитектура разделена на два уровня:

Домен (Инфраструктура): папка app/<domain>/. Настройки, привязки хостов, глобальные ресурсы.

Алиас (Контент): папка app/<domain>/<allias>/ (например, www, doc). Конкретные страницы.

Инфраструктурные папки домена скрыты точкой:
- .alliasglobal/ — глобальные блоки (регионы);
- .alliasscheme/ — глобальные схемы (каркасы).

Правило 3 файлов: Любая логическая сущность (Страница, Блок, Схема) состоит из трех файлов:
1. {name}.php — PHP-код.
2. {name}.rules.json — Настройки (auth, type, enabled).
3. {name}.editor.json — Данные для UI (деревья вложенности).

Термины — по MANIFEST.md §2. Не используйте «модуль», «плагин», «виджет», «шаблон».

2. Создание сайта (Домена)
Через CLI:
bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
Алиас www создаётся автоматически. Отдельный allias:new для него не нужен.

Что создаётся:
text
app/example.ru/
├── .alliasglobal/
├── .alliasscheme/
└── www/
    └── algoritm/
        ├── algoritm.rules.json
        ├── algoritm.editor.json
        └── algoritm.php

3. Создание дополнительных алиасов
bash
php bin/cli.php allias:new --domain=example.ru --name=doc
Что создаётся:
text
app/example.ru/doc/algoritm/
├── algoritm.rules.json
├── algoritm.editor.json
└── algoritm.php

На один домен — сколько угодно алиасов. Но на один IP-хост без DNS работает только один алиас (www), потому что алиас определяется по Host.

4. Создание страниц и логики
Создание через CLI:
bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
Создаётся папка about/ с контроллером и файлами правил:
text
app/example.ru/www/algoritm/
├── algoritm.rules.json
├── algoritm.php
└── about/
    ├── algoritm_about.rules.json
    ├── algoritm_about.editor.json
    └── algoritm_about.php       (класс algoritm_about)

⚠️ Ключевое: страница без схемы vs со схемой
page:new создаёт страницу, которая не рендерит схему по умолчанию. Шапки, футера и других регионов на такой странице не будет, только возвращённый Node. У вас есть два варианта ручной страницы.

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
Это осознанный режим для API-эндпоинтов, ajax-ответов и подобного. Но не для обычных страниц сайта.

Ручное редактирование кода
В админке — кнопка «Код» (CodeMirror). Напрямую в файле — обычным редактором. Namespace: <домен_с_подчёркиваниями>\<allias>. Класс — algoritm_<путь_с_подчёркиваниями>. Вывод — только через Node или Response. Никаких echo, print, var_dump.

5. Создание блоков (Регионов)
bash
php bin/cli.php block:new --domain=example.ru --name=header --type=mixed
Типы блоков (хранятся в header.rules.json):
- visual — только вёрстка (статичный HTML, контейнер);
- script — только скрипт (возвращает данные, не участвует в визуализации);
- mixed — реализация (данные + вёрстка, конечный лист).

Что создаётся:
text
app/example.ru/.alliasglobal/header/
├── header.php       (класс alliasglobal_header implements RegionInterface)
├── header.rules.json
└── header.editor.json

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
⚠️ Визуального редактора для блоков больше нет. Если нужна логика (custom/if) — включите security.allow_php_blocks=true в engine.config (по умолчанию заблокировано для безопасности).

6. Создание Схем (Каркасов)
Визуальный редактор Схем работает на Drag&Drop. Разделяет блоки на зоны: Неактивные, Скрипты (только script), Визуал (visual и mixed). Поддерживает вложенность.
Схема состоит из трёх файлов:
- www.php (рендер)
- www.rules.json (настройки)
- www.editor.json (дерево для Drag&Drop)

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
⚠️ Document::release() идемпотентен (stage-4), но вызывайте его один раз в PageHandler.

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

service('db') возвращает mixed. Всегда проверяйте instanceof. Миграции — только через CLI: php bin/cli.php db:migrate.

10. Авторизация и Безопасность
Чтобы страница требовала вход, добавьте "auth": true в algoritm_{name}.rules.json (через CLI: page:toggle --key=auth).

В коде используйте удобные обертки базового класса:

php
if ($this->isAuthenticated()) {
    $user = $this->user();
}

Все POST — с CSRF. Для форм используйте компонент Form::create(...)->csrf($this->context->service('csrf')->field()).
Пользователи сайта (для страниц с auth: true) создаются через user:new. Админка использует отдельную авторизацию (config/admin.config).

11. Использование AJAX
Если запрос с X-Requested-With: XMLHttpRequest — можно вернуть JSON:

php
use Damoony\Response\MinStruct;

if ($this->isAjax()) {
    $struct = new MinStruct();
    $struct->messages('<div class="ok">Успешно</div>');
    return $struct->toResponse();
}

12. Филлеры (переопределение блоков)
Чтобы изменить шапку только для одного алиаса:

bash
php bin/cli.php filler:new --domain=example.ru --allias=admin --block=header

Создаются три файла: header_admin.php, header_admin.rules.json, header_admin.editor.json. Филлер включается автоматически.
Управление (выключить):
bash
php bin/cli.php filler:toggle --domain=example.ru --allias=admin --block=header --value=0

13. Диагностика
bash
php bin/cli.php structure:check
Проверяет: наличие всех 3 файлов для каждого элемента, стыковку филлер↔слот, схемы. Если есть пропуски — восстановить можно через:
bash
php bin/cli.php structure:repair --domain=example.ru

14. Чек-лист перед деплоем страницы
1. Схема: страница рендерит схему (Вариант A) или это осознанный API-эндпоинт (Вариант B)?
2. Правило 3 файлов: У страницы есть .php, .rules.json, .editor.json?
3. default(): не возвращает null.
4. POST: с CSRF. Destructive: через POST+CSRF.
5. structure:check: зелёный.
6. git: коммит перед изменениями.

Подробнее о состоянии проекта — MANIFEST.md. О командах — cli.md. Об админке — admin.md. О жизненном цикле запроса — architecture.md.

--- КОНЕЦ ДОКУМЕНТА ---