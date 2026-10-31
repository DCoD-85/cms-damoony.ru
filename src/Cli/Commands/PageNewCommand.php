<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class PageNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'page:new';
    }

    public function description(): string
    {
        return 'Создать страницу алиаса (генерирует php, rules.json, editor.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $name = $args['name'] ?? '';
        $parent = trim((string)($args['parent'] ?? ''), '/');

        // 1. Валидация аргументов
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя страницы (только латиница, цифры, подчеркивания)');
            return $r;
        }
        if ($parent !== '' && !preg_match('/^[a-z][a-z0-9_\/]*$/', $parent)) {
            $r->error('Некорректный путь родительской страницы');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();

        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден в конфиге');
            return $r;
        }

        $namespace = (string) ($site['domains_namespace'][$domain] ?? str_replace(['.', '-'], '_', $domain));

        // 2. Определяем пути и классы
        $baseAlgoritmDir = $this->paths->algoritm($domain, $allias);

        // Папка для новой страницы
        $targetRelPath = $parent === '' ? $name : $parent . '/' . $name;
        $pageDir = $baseAlgoritmDir . '/' . $targetRelPath;

        if (Fs::isDir($pageDir)) {
            $r->error('Страница уже существует');
            return $r;
        }

        // Формируем имя класса: algoritm_about или algoritm_docs_about
        $classChain = 'algoritm_' . str_replace('/', '_', $targetRelPath);

        // 3. Создаём папку
        Fs::mkdir($pageDir);

        // 4. Создаём algoritm_<name>.php
        $php = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$allias};\n\n"
            . "use Damoony\\Algoritm\\Page;\n"
            . "use Damoony\\Ui\\Builder;\n"
            . "use Damoony\\Ui\\Node;\n"
            . "use Damoony\\Http\\Response;\n\n"
            . "final class {$classChain} extends Page\n"
            . "{\n"
            . "    public function default(): Node|Response|null\n"
            . "    {\n"
            . "        return Builder::div('page')->content('Страница {$name}');\n"
            . "    }\n"
            . "}\n";

        $phpFile = $pageDir . '/' . $classChain . '.php';
        Fs::writeAtomic($phpFile, $php);
        $r->file(Fs::rel($phpFile, $this->paths->root()));

        // 5. Создаём algoritm_<name>.rules.json
        $rulesFile = $pageDir . '/' . $classChain . '.rules.json';
        Fs::writeAtomic($rulesFile, json_encode(['available' => []], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($rulesFile, $this->paths->root()));

        // 6. Создаём algoritm_<name>.editor.json
        $editorFile = $pageDir . '/' . $classChain . '.editor.json';
        Fs::writeAtomic($editorFile, json_encode([], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($editorFile, $this->paths->root()));

        // 7. ОБНОВЛЯЕМ РОУТИНГ РОДИТЕЛЯ (добавляем $name в available)
        $parentDir = $parent === '' ? $baseAlgoritmDir : $baseAlgoritmDir . '/' . $parent;
        $parentRulesFile = $parentDir . '/.rules.json';

        $parentRules = [];
        if (is_file($parentRulesFile)) {
            $parentRules = json_decode((string)file_get_contents($parentRulesFile), true) ?: [];
        }

        if (!isset($parentRules['available']) || !is_array($parentRules['available'])) {
            $parentRules['available'] = [];
        }

        if (!in_array($name, $parentRules['available'], true)) {
            $parentRules['available'][] = $name;
            // Если это корень, то корневой класс — algoritm (без суффикса)
            if ($parent === '') {
                $parentRules['available'][] = ''; // "" означает саму корневую страницу
                $parentRules['available'] = array_values(array_unique($parentRules['available']));
            }
            Fs::writeAtomic($parentRulesFile, json_encode($parentRules, JSON_PRETTY_PRINT) . "\n");
            $r->file(Fs::rel($parentRulesFile, $this->paths->root()));
        }

        $context->step('Страница создана', true, $targetRelPath);

        return $r;
    }
}
