<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use Damoony\Page\PagePath;

final class PageReadCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'page:read';
    }

    public function description(): string
    {
        return 'Прочитать PHP-код страницы';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $name = $args['name'] ?? '';
        $parent = trim((string) ($args['parent'] ?? ''), '/');

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }

        // 2. __root__ — для главной страницы алиаса
        if ($name === '__root__') {
            if ($parent !== '') {
                $r->error('--parent несовместим с __root__');
                return $r;
            }
            $name = '';
        } elseif ($name === '') {
            $r->error('Укажите --name (или __root__ для главной страницы)');
            return $r;
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя страницы (a-z, 0-9, _; начинается с буквы)');
            return $r;
        }

        if ($parent !== '' && !preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#', $parent)) {
            $r->error('Некорректный путь родительской страницы');
            return $r;
        }

        // 3. Резолвинг
        $page = PagePath::resolve($this->paths, $domain, $allias, $name, $parent);

        if (!is_file($page->phpFile)) {
            $r->error('Страница не найдена: ' . $page->phpFile);
            return $r;
        }

        $code = file_get_contents($page->phpFile);
        if ($code === false) {
            $r->error('Не удалось прочитать файл');
            return $r;
        }

        $context->step($code, true, $name !== '' ? $name : '__root__');

        return $r;
    }
}
