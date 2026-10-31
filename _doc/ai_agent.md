--- НАЧАЛО ДОКУМЕНТА ---

Инструкция для AI-Агента (Универсальная)
Ты — AI-агент, работающий с Damoony Engine. Этот документ объединяет правила для архитектора (инфраструктура), разработчика (PHP-код) и интегратора (безопасный фасад aicli.php).

Перед началом прочитай MANIFEST.md. Там — что реализовано (✅), что частично (🟡). Все P0 и P1 закрыты. Остались только задачи из P2 (техдолг).

Правило 3 файлов: Любая сущность (Страница, Блок, Схема) состоит из трех файлов: {name}.php (код), {name}.rules.json (настройки), {name}.editor.json (данные UI). Глобальные манифесты (blocks.json, schemes.json) удалены.

---
ЧАСТЬ 1: АРХИТЕКТУРА И ИНФРАСТРУКТУРА (System)
Твоя задача: создавать домены, алиасы, блоки, схемы. Ты не пишешь вёрстку и не трогаешь ядро (src/).

1.1. Главные правила
- **Только CLI.** Любая операция с файловой структурой — через php bin/cli.php.
- **Только JSON.** Настройки изменяются только в config/*.config.
- **Запрещено:** удалять файлы вручную (только через CLI), писать код в src/, использовать прямые SQL-запросы.
- **Коммит в git** перед destructive-операциями (allias:remove, block:remove, scheme:remove, trash:clear --force).
- **Визуальные редакторы (Drag&Drop)** остались ТОЛЬКО для Схем. Страницы и Блоки редактируются исключительно через CodeMirror (PHP-код).

1.2. Структура файлов
- Домен: папка app/<domain>/.
- Алиасы (www, doc) — подпапки внутри домена.
- Инфраструктурные папки скрыты точкой: .alliasglobal (блоки), .alliasscheme (схемы).

Структура сущностей:
- **Блок:** .alliasglobal/<name>/<name>.php, <name>.rules.json, <name>.editor.json.
- **Схема:** .alliasscheme/<name>.php, <name>.rules.json, <name>.editor.json.
- **Страница:** algoritm/algoritm_<name>.php, algoritm_<name>.rules.json, algoritm_<name>.editor.json.

Что не читается движком (не рассчитывай): .data.php и .data.json (удалены).

1.3. Справочник команд (только CLI)
Домены и алиасы:
- domain:new --domain=example.ru [--namespace=example] — создать домен.
- allias:new --domain=example.ru --name=doc — создать алиас.
- allias:remove --domain=example.ru --name=doc --force — удалить (карантин).

Блоки, схемы, филлеры:
- block:new --domain=example.ru --name=top [--type=visual] — создать блок (3 файла).
- block:remove --domain=example.ru --name=top — удалить папку блока.
- filler:new --domain=example.ru --allias=doc --block=top — создать филлер (3 файла).
- filler:toggle --domain=example.ru --allias=doc --block=top --value=0 — вкл/выкл филлер.
- scheme:new --domain=example.ru --name=www — создать схему (3 файла).
- scheme:copy --domain=example.ru --name=www — копировать схему.
- scheme:remove --domain=example.ru --name=www — удалить схему.
- scheme:save --domain=example.ru --name=www --scheme_json='...' — сохранить визуальную схему.

Страницы:
- page:new --domain=example.ru --allias=doc --name=index [--parent=path] — создать страницу.
- page:toggle --domain=example.ru --allias=doc --path=index --key=auth --value=1 — флаг (auth/search/default).
⚠️ page:new создаёт страницу без схемы по умолчанию. Агент должен добавить вызов SchemeLoader + SchemeRenderer в default() (см. Часть 2).

Диагностика:
- structure:check — проверить целостность (наличие всех 3 файлов, стыковку).
- structure:repair — восстановить отсутствующие .rules.json и .editor.json.

1.4. Управление конфигом (site.config)
Отключить филлер:
```json
"fillers": { "example.ru": { "doc": { "top": false } } }
```
Отключить глобальный регион:
```json
"regions": { "example.ru": { "doc": { "left": false } } }
```
Отключить алиас (503):
```json
"alliases_available": { "example.ru": { "doc": false } }
```
⚠️ Обязательно: self-mapping в domains_real ("example.ru": "example.ru"). Без него CLI упадёт.

---
ЧАСТЬ 2: НАПИСАНИЕ PHP-КОДА (Logic)
Твоя задача: писать код для страниц (algoritm/) и регионов (.alliasglobal/). Ты не трогаешь конфиги и ядро.

2.1. Главные правила кода
- **Никаких echo, print, var_dump.** Вывод — только через возврат Node или Response.
- **Никакого HTML в строках.** Используй Builder:: (12 методов) или new Element() для сложных структур. Для таблиц/форм — компоненты Table, Form.
- **Только строгая типизация.** declare(strict_types=1); + namespace в каждом файле.
- **Проверяй типы из service().** $this->context->service('...') возвращает mixed — всегда instanceof перед использованием.
- **Возвращай Node или Response.** Не возвращай null (рантайм отдаёт 204, но лучше явный Node).

2.2. Шаблон контроллера страницы (Algoritm)
⚠️ **Ключевое: страница со схемой vs без схемы.**
page:new создаёт "голую" страницу (только класс Algoritm), которая НЕ рендерит схему. Если нужны шапка/футер — ты ОБЯЗАН явно вызвать SchemeLoader + SchemeRenderer (Вариант A). Если это API — можно без схемы (Вариант B).

**Вариант A: страница со схемой (рекомендуется)**
```php
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
```

**Вариант B: «голая» страница (API, AJAX)**
```php
final class algoritm_api extends Algoritm
{
    public function default(): Node|Response|null
    {
        return Builder::div('api')->content('ok');
    }
}
```

2.3. Шаблон региона (.alliasglobal)
Все блоки реализуют RegionInterface. Настройки типа блока (script, visual, mixed) хранятся в {name}.rules.json, но в рантайме RegionLoader читает папки.

```php
<?php

declare(strict_types=1);

namespace example_ru;

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

2.4. Формы (POST) и CSRF
```php
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
```
✅ Destructive-операции — только POST+CSRF. GET-delete не работают (405).

2.5. Работа с БД
```php
$db = $this->context->service('db');
if (!$db instanceof \Damoony\Db\Connection) {
    return Response::internalError('DB service missing');
}

$stmt = $db->pdo()->query('SELECT * FROM news ORDER BY created_at DESC');
$news = $stmt->fetchAll();
```

2.6. ⚠️ Чего не делать в коде
- **addslashes** — только var_export($content, true).
- **str_replace('array (', ...)** — только var_export($data, true).
- **assert()** — заменяй на if (!instanceof) throw new \RuntimeException(...).
- **$_COOKIE['skin'] напрямую** — используй $this->context->domain->skin.
- **Сырой PHP в блоках** — для custom/if требуется security.allow_php_blocks=true в engine.config. По умолчанию заблокировано.

2.7. Ограничения Builder (P2)
Builder содержит 12 методов: el, elA, div, span, p, h, a, img, input, ul, li, button. Нет: table, tr, td, form, select, textarea, label, section, nav, header, footer, article, aside. Для них — new Element('tag') или компоненты (Table, Form, Field).

---
ЧАСТЬ 3: AI-ФАСАД И ИНТЕГРАЦИЯ (Agent API)
Твоя задача: работать через безопасный интерфейс bin/aicli.php, который связывает LLM (Ollama) с ядром.

3.1. Архитектура (Agentic Loop)
Агент не имеет прямого доступа к терминалу или файловой системе. Он работает через безопасный цикл:

[AI-Модель (Ollama)] 
      ↕ (JSON: tool_calls)
[Python/Node Скрипт (Agentic Loop)] 
      ↕ (JSON через STDIN)
[bin/aicli.php (Безопасный фасад)] 
      ↕ (Вызов PHP-класса)
[bin/cli.php (SAFE-CLI)]
      ↕
[Файловая система / config/]

- **Agentic Loop:** Скрипт-обертка отправляет промпт в Ollama, получает tool_calls, передает их в aicli.php, получает результат, возвращает в модель, и так до финального ответа.
- **Разделение прав:** Агент не может выполнить system('rm -rf /'), потому что aicli.php принимает только разрешенные команды (Whitelist) и строго валидирует аргументы.

3.2. Спецификация bin/aicli.php
Читает JSON из STDIN, возвращает JSON в STDOUT.

Формат запроса:
```json
{"command": "engine_status", "args": {}}
{"command": "filler_toggle", "args": {"domain": "damoony.ru", "allias": "doc", "block": "top", "value": "0"}}
```

Формат ответа:
```json
{"ok": true, "data": {"status": "OK", "message": "Structure is perfect."}}
{"ok": false, "error": "Structure check failed", "data": {"errors_count": "1", "raw_output": "..."}}
```

3.3. Whitelist (Разрешенные команды)
Список находится в bin/aicli.php (массив $allowedCommands).
- read_test_file — Чтение тестового файла из /var/ (защита от Path Traversal).
- engine_status — Запуск structure:check.
- filler_toggle — Включение/выключение филлера.

Запрещено: деструктивные команды (allias:remove, db:migrate, user:new).

3.4. Безопасность AI-слоя
- **Валидация аргументов:** Все аргументы проверяются через строгие Regex. Если агент передаст ../../etc/passwd, скрипт вернет ошибку.
- **Жесткий вызов:** Команды вызываются через shell_exec только с валидированными аргументами.
- **Изоляция (Roadmap):** В будущем агент будет иметь права на создание только test-* доменов.

3.5. Как расширять (Добавление новых команд)
Чтобы дать агенту новую возможность (например, domain:new):
1. Убедитесь, что команда в bin/cli.php безопасна и идемпотентна.
2. Добавьте её в $allowedCommands в bin/aicli.php.
3. Напишите строгую проверку аргументов (regex) для этой команды.
4. Добавьте описание инструмента (Tool) в Python-скрипт (agent_test.py), чтобы Ollama знала о ней.

3.6. Пример работы (Автономное лечение)
1. AI вызывает engine_status. Ответ: {"ok": false, "raw_output": "...Филлер top включен, но слота top нет..."}.
2. AI читает ошибку и вызывает filler_toggle с args: {"domain": "damoony.ru", "allias": "doc", "block": "top", "value": "0"}.
3. aicli.php валидирует аргументы и запускает php bin/cli.php filler:toggle ...
4. AI получает {"ok": true} и пишет финальный отчет: "Я обнаружил ошибку и отключил филлер top".

---
ЧЕК-ЛИСТ ПЕРЕД СДАЧЕЙ
1. declare(strict_types=1); + namespace.
2. Нет echo/print/var_dump.
3. default() возвращает Node или Response.
4. Если страница обычная — вызван SchemeRenderer.
5. service('...') — с instanceof.
6. Формы — с CSRF. Destructive — POST.
7. Нет addslashes, str_replace('array (', ...), assert().
8. Для инфраструктуры — использован CLI, а не ручное создание файлов.
9. Коммит в git перед правкой существующих файлов.

ССЫЛКИ
MANIFEST.md — канон (✅/🟡/❌, P0/P1/P2).
cli.md — полный справочник команд.
architecture.md — жизненный цикл (включая AI-слой).
contracts.md — контракты и ограничения.
guide.md — руководство разработчика (Вариант A/B, блоки, схемы).

--- КОНЕЦ ДОКУМЕНТА ---