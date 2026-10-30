<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;

final class BlockRemoveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'block:remove';
    }

    public function description(): string
    {
        return 'Удалить блок alliasglobal';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string)($args['domain'] ?? '');
        $name = (string)($args['name'] ?? '');

        if ($domain === '' || $name === '') {
            $r->error('Укажите --domain и --name');
            return $r;
        }

        // ВАЖНО: Защита от Path Traversal (выход за пределы .alliasglobal)
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя блока');
            return $r;
        }

        $blockDir = $this->paths->domain($domain) . '/.alliasglobal/' . $name;
        if (!is_dir($blockDir)) {
            $r->error('Блок не найден');
            return $r;
        }

        $this->rmrf($blockDir);
        $context->step("Блок {$name} удален", true);
        return $r;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
