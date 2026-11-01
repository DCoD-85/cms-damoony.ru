--- НАЧАЛО ДОКУМЕНТА ---

Руководство разработчика
В этом руководстве описано, как с нуля создать сайт, добавить страницы, блоки, схемы и написать логику. Разработка ведётся тремя путями: через CLI (инфраструктура), через JSON-контент (для AI-агентов) или через PHP-код (для разработчиков). Перед началом прочитайте MANIFEST.md.

⚠️ Важно перед стартом. Визуальные редакторы для Страниц и Блоков удалены в целях безопасности (предотвращение RCE). Всё редактирование кода происходит через встроенный CodeMirror. Визуальный Drag&Drop оставлен ТОЛЬКО для Схем.

Все destructive CLI-операции (domain:remove, allias:remove, block:remove, scheme:remove, trash:clear) работают через карантин (`*_trash_<timestamp>/`) и требуют `--force`. Перед ними — коммит в git.

1. Концепция и структура
Архитектура разделена на два уровня:

Домен (Инфраструктура): папка app/<domain>/. Настройки, привязки хостов, глобальные ресурсы.

Алиас (Контент): папка app/<domain>/<allias>/ (например, www, doc). Конкретные страницы.

Инфраструктурные папки домена скрыты точкой:
- .alliasglobal/ — глобальные блоки (регионы);
- .alliasscheme/ — глобальные схемы (каркасы).

Правило 3 файлов: Любая логическая сущность (Страница, Блок, Схема) состоит из трех файлов:
1. {name}.php — PHP-код (класс или массив).
2. {name}.rules.json — Настройки (auth, type, enabled, available).
3. {name}.editor.json — Данные для UI (деревья вложенности).

Для страниц все три файла имеют единый префикс algoritm_{name}.{php,rules.json,editor.json} в одной папке:
- root: algoritm/algoritm.{php,rules.json,editor.json}
- /about/: algoritm/about/algoritm_about.{php,rules.json,editor.json}
- /docs/install/: algoritm/docs/install/algoritm_docs_install.{php,rules.json,editor.json}

realDomain: если в site.config есть `domains_real["damoony3.ru"] = "damoony.ru"`, то папка в app/ называется `damoony.ru`, а не `damoony3.ru`. Все CLI-команды (block/filler/scheme/domain) резолвят realDomain перед построением пути.

Термины — по MANIFEST.md §2.

2. Создание сайта (Домена)
Через CLI:
bash
php bin/cli.php domain:new --domain=example.ru --namespace=example
Алиас www создаётся автоматически. Отдельный allias:new для него не нужен.

Что создаётся:
text
app/example.ru/
├── .alliasglobal/center/  (center.php, center.rules.json, center.editor.json)
├── .alliasscheme/         (www.php, www.rules.json)
└── www/
    └── algoritm/
        ├── algoritm.php              (через PageCompiler)
        ├── algoritm.rules.json       (полные дефолты available/auth/search/default)
        └── algoritm.editor.json

Обновляется site.config: domains_real, domains_available, domains_namespace, alliases_real, alliases_available.

⚠️ Path traversal guard: `--domain=a..ru` отклоняется.

3. Создание дополнительных алиасов
bash
php bin/cli.php allias:new --domain=example.ru --name=doc
Что создаётся:
text
app/example.ru/doc/algoritm/
├── algoritm.php            (через PageCompiler)
├── algoritm.rules.json
└── algoritm.editor.json

Обновляется site.config: alliases_real, alliases_available.

На один домен — сколько угодно алиасов. Но на один IP-хост без DNS работает только один алиас (www), потому что алиас определяется по Host.

4. Создание страниц и наполнение контентом
Создание каркаса страницы через CLI:
bash
php bin/cli.php page:new --domain=example.ru --allias=www --name=about
Создаётся папка about/ с тремя файлами (algoritm_about.php, algoritm_about.rules.json, algoritm_about.editor.json) и обновляется available в родительских правилах (root: algoritm/algoritm.rules.json).

⚠️ Родительская страница должна существовать. `page:new --parent=ghost` → ошибка «Родительская папка не найдена».

⚠️ Ключевое: страница без схемы vs со схемой
page:new создаёт страницу, которая не рендерит схему по умолчанию. Шапки, футера и других регионов на такой странице не будет, только возвращённый Node.

Вариант A: страница со схемой (рекомендуется)
Наследуйтесь от HomePage (он уже вызывает SchemeLoader + SchemeRenderer) или сами вызовите рендерер. Класс теперь называется Page.

php
<?php

declare(strict_types=1);

namespace example_ru\www;

use Damoony\Algoritm\Page;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Node;

final class algoritm_about extends Page
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

Вариант B: «голая» страница без схемы (API, AJAX)
php
final class algoritm_api extends Page
{
    public function default(): Node|Response|null
    {
        return Builder::div('api-answer')->content('ok');
    }
}

4.1. Наполнение контентом через JSON (Безопасный метод)
Для AI-агентов и автоматизации внедрен безопасный метод записи контента. Вы не пишете PHP-код (RCE), а передаете JSON-массив.
bash
php bin/cli.php page:write --domain=example.ru --allias=www --name=about --json='[{"type":"h1","content":"О нас"},{"type":"text","content":"Текст"}]'
- Поддерживаемые типы: h1, h2, h3, text, img, link, button, ul.
- Поддерживаемые атрибуты: class, id, style, url (для link), alt (для img).
- Специальное имя `__root__` используется для перезаписи главной страницы аллиаса (/).
- Флаг `--with_schema=1` — оборачивает контент в глобальную схему (подтягивает шапку/подвал).
- `--parent` несовместим с `__root__` (главная страница всегда в корне).

Ручное редактирование кода
В админке — кнопка «Код» (CodeMirror). Namespace: <домен_с_подчёркиваниями>\<allias>. Класс — algoritm_<путь>. Вывод — только через Node или Response.

4.2. Переключение флагов страницы
bash
php bin/cli.php page:toggle --domain=example.ru --allias=www --name=about --key=auth --value=1
- --key — auth, search или default.
- --value — 0 или 1.
- --parent — опционально, для вложенных.
Для главной страницы — --name='__root__'.
⚠️ Аргумент --path из старой версии не поддерживается (breaking change после унификации модели). Пишет в algoritm_{name}.rules.json с полным набором полей.

5. Создание блоков (Регионов)
bash
php bin/cli.php block:new --domain=example.ru --name=header --type=mixed
Типы блоков: visual (вёрстка), script (скрипт), mixed (реализация + вёрстка).

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

⚠️ Визуального редактора для блоков нет. Если нужна логика (custom/if) — включите security.allow_php_blocks=true в engine.config (по умолчанию заблокировано).

Удаление блока — в карантин:
bash
php bin/cli.php block:remove --domain=example.ru --name=header --force
Папка .alliasglobal/header/ перемещается в header_trash_<timestamp>/. Без --force — отмена. Очистка карантина — trash:clear.

6. Создание Схем (Каркасов)
Визуальный редактор Схем работает на Drag&Drop. Разделяет блоки на зоны: Неактивные, Скрипты (только script), Визуал (visual и mixed). Поддерживает вложенность.
Схема состоит из трёх файлов:
- www.php (рендер)
- www.rules.json (настройки)
- www.editor.json (дерево для Drag&Drop)

CLI-команды для схем:
- scheme:new — создать (3 файла).
- scheme:copy — копировать в scheme_copy / scheme_copy_1 / ... (3 файла).
- scheme:remove --force — в карантин (.alliasscheme/scheme_trash_<ts>/).
- scheme:save — сохранить визуальную схему (обновить editor.json + компилировать php через SchemeCompiler).

7. Управление регионами из кода (Runtime API)
Внутри default():

php
// Отключить шапку на этой странице
$this->regions()->disable('header');

// Принудительно использовать глобальный блок
$this->regions()->useGlobal('header');

// Подменить содержимое слота
$this->setContent('center', $myNode);

Приоритет в RegionRegistry::get(): disabled → forcedGlobal → filler → base.

8. Мета-информация (CSS, JS, Title)
php
$this->context->meta->setTitle('Страница');
$this->context->meta->addCss('/media/example.ru/skin/default/css/style.css');
$this->context->meta->addJs('/media/example.ru/skin/default/js/app.js');
$this->context->meta->addMeta('description', 'Описание страницы');

9. Работа с базой данных
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
Миграции — только через CLI: php bin/cli.php db:migrate.
⚠️ --dir ограничен подпапками migrations/ (path traversal guard).

10. Авторизация и Безопасность
Чтобы страница требовала вход, добавьте "auth": true в algoritm_{name}.rules.json (через CLI: page:toggle --key=auth).

В коде используйте обертки базового класса:

php
if ($this->isAuthenticated()) {
    $user = $this->user();
}

Все POST — с CSRF. Пользователи сайта создаются через user:new. Админка использует отдельную авторизацию.

Валидация:
- admin:passwd — login 3-32 [a-zA-Z0-9_.-], password минимум 8 символов.
- user:new — login 3-32 [a-zA-Z0-9_], email FILTER_VALIDATE_EMAIL, password минимум 8, уникальность login и email.

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
bash
php bin/cli.php filler:new --domain=example.ru --allias=admin --block=header

Создаются три файла: header_admin.php, header_admin.rules.json, header_admin.editor.json. Филлер включается автоматически (site.config: fillers[domain][allias][block] = true).
Проверяется, что алиас и блок существуют (конфиг + диск).

Выключить: filler:toggle --domain=example.ru --allias=admin --block=header --value=0
Включить: --value=1.
Idempotent: если значение не меняется — файл site.config не перезаписывается. Блок проверяется на диске (не пишем мусор в конфиг).

13. Диагностика и миграция
bash
php bin/cli.php structure:check
Severity-aware. Проверяет:
- наличие всех 3 файлов для каждой страницы (algoritm_{name}.{php,rules.json,editor.json});
- наличие algoritm/algoritm.rules.json (root);
- отсутствие устаревших {dir}/.rules.json;
- стыковку филлер↔слот.

`✗` (error) — критично (exit 1): отсутствует .php, отсутствует root .rules.json, отсутствует схема.
`⚠` (warning) — некритично (exit 0): отсутствует .editor.json, устаревший .rules.json, филлер без слота.

Восстановить пропуски (root-страницы через PageCompiler, .rules.json/.editor.json для блоков и схем):
bash
php bin/cli.php structure:repair --domain=example.ru

Перевести старые .rules.json на новую модель (если видите warning про устаревшие):
bash
php bin/cli.php migrate:page-rules --dry   # предпросмотр
php bin/cli.php migrate:page-rules          # боевой прогон
Идемпотентна. Шаги: (1) root algoritm/.rules.json → algoritm/algoritm.rules.json, (2) подпапки {dir}/.rules.json → {dir}/algoritm_{name}.rules.json, (3) нормализация полей, (4) создание пустых .editor.json.

Очистка карантина:
bash
php bin/cli.php trash:clear                          # dry-run
php bin/cli.php trash:clear --force                  # удалить всё
php bin/cli.php trash:clear --force --older=7d       # только старше 7 дней
php bin/cli.php trash:clear --force --domain=example.ru  # только один домен
Обходит 4 уровня карантина: app/, app/<domain>/, .alliasscheme/, .alliasglobal/.

14. Чек-лист перед деплоем страницы
1. Схема: страница рендерит схему (Вариант A) или это осознанный API-эндпоинт (Вариант B)?
2. Правило 3 файлов: у страницы есть algoritm_{name}.php, algoritm_{name}.rules.json, algoritm_{name}.editor.json (единый префикс)? Для root — algoritm/algoritm.{php,rules.json,editor.json}.
3. default(): не возвращает null (или осознанно — 204 No Content).
4. POST: с CSRF. Destructive: через POST+CSRF (UI) или --force (CLI).
5. structure:check: зелёный (без errors; warnings допустимы).
6. git: коммит перед изменениями (особенно перед destructive CLI-командами).
7. realDomain: если domain — входной хост, проверь, что папка в app/ называется по realDomain.

15. Path traversal и безопасность CLI
Все CLI-команды с `--domain`/`--name` проверяют:
- regex: domain `[a-z0-9][a-z0-9.-]*\.[a-z]{2,}`, name `[a-z][a-z0-9_]*`.
- `str_contains($domain, '..')` → error.

Destructive-команды (domain:remove, allias:remove, block:remove, scheme:remove, trash:clear) требуют `--force`. Работают через карантин (`*_trash_<timestamp>/`).

Idempotent remove: нет папки + нет в конфиге → warning, exit 0.

Полная очистка конфига:
- domain:remove — 16 ключей (domains_*, alliases_*, fillers).
- allias:remove — 8 ключей (alliases_*, fillers).

Подробнее о состоянии проекта — MANIFEST.md. О командах — cli.md. Об админке — admin.md. О жизненном цикле запроса — architecture.md.

--- КОНЕЦ ДОКУМЕНТА ---