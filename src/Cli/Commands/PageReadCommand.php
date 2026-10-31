<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;

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
        $parent = trim((string)($args['parent'] ?? ''), '/');

        if ($domain === '' || $allias === '' || $name === '') {
            $r->error('Укажите --domain, --allias и --name');
            return $r;
        }

        $targetRelPath = $parent === '' ? $name : $parent . '/' . $name;
        $classChain = 'algoritm_' . str_replace('/', '_', $targetRelPath);
        $file = $this->paths->algoritm($domain, $allias) . '/' . $targetRelPath . '/' . $classChain . '.php';

        if (!is_file($file)) {
            $r->error('Страница не найдена');
            return $r;
        }

        // Возвращаем сам код через контекст
        $code = file_get_contents($file);
        $context->step($code !== false ? $code : 'Error reading file', true);

        return $r;
    }
}
