<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PageNamespace;
use Damoony\UI\Page\PagePath;
use Damoony\UI\Page\PageRules;

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

        if (function_exists('stream_isatty') && @stream_isatty(STDIN)) {
            if ($domain === '') {
                echo 'Введите домен (например, damoony.ru): ';
                $domain = trim((string) fgets(STDIN));
            }
            if ($allias === '') {
                echo 'Введите алиас (по умолчанию www): ';
                $input = trim((string) fgets(STDIN));
                $allias = $input === '' ? 'www' : $input;
            }
            if ($name === '') {
                echo 'Введите имя страницы (например, about): ';
                $name = trim((string) fgets(STDIN));
            }
        }
        $parent = trim((string) ($args['parent'] ?? ''), '/');
        $sessionId = trim((string) ($args['session'] ?? ''));

        // 1. Валидация аргументов
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            $r->error('Некорректное имя страницы (только латиница, цифры, подчеркивания)');
            return $r;
        }
        if ($parent !== '' && !preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#', $parent)) {
            $r->error('Некорректный путь родительской страницы');
            return $r;
        }

        // 2. Загрузка site.config
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
        if (!isset($site['alliases_real'][$domain][$allias])) {
            $r->error('Алиас не найден в конфиге');
            return $r;
        }

        // 3. Резолвинг путей
        $page = PagePath::resolve(
            $this->paths,
            $domain,
            $allias,
            $name,
            $parent,
            $sessionId !== '' ? $sessionId : null,
        );

        if (Fs::isDir($page->pageDir)) {
            $r->error('Страница уже существует');
            return $r;
        }

        // 4. Проверка родителя
        if (!Fs::isDir($page->parentDir)) {
            $r->error('Родительская папка не найдена: ' . $page->parentRelPath);
            return $r;
        }
        if (!is_file($page->parentPhpFile)) {
            $r->error('Родительская страница не найдена (нет файла ' . basename($page->parentPhpFile) . ')');
            return $r;
        }

        // 5. Создание .php через PageCompiler (пустой stub)
        $namespace = PageNamespace::fromSite($site, $domain);
        $compiler = new PageCompiler();
        $php = $compiler->compile($namespace . '\\' . $allias, $page->classChain, []);

        Fs::writeAtomic($page->phpFile, $php);
        $r->file(Fs::rel($page->phpFile, $this->paths->root()));

        // 6. Создание .rules.json (полные дефолты)
        PageRules::save($page->rulesFile, PageRules::defaults());
        $r->file(Fs::rel($page->rulesFile, $this->paths->root()));

        // 7. Создание .editor.json (пустой — визуального редактора страниц нет)
        Fs::writeAtomic($page->editorFile, "[]\n");
        $r->file(Fs::rel($page->editorFile, $this->paths->root()));

        // 8. Обновление available в правилах родителя
        $parentRules = PageRules::load($page->parentRulesFile);
        $parentRules = PageRules::withChild($parentRules, $name);
        PageRules::save($page->parentRulesFile, $parentRules);
        $r->file(Fs::rel($page->parentRulesFile, $this->paths->root()));

        $context->step('Страница создана', true, $page->targetRelPath);

        return $r;
    }
}
