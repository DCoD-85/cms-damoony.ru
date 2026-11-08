<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class ContentRemoveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'content:remove';
    }

    public function description(): string
    {
        return 'Удалить контент в карантин (/content/.trash/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $category = (string) ($args['category'] ?? '');
        $hash = (string) ($args['hash'] ?? '');
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $category)) {
            $r->error('Некорректная category');
            return $r;
        }
        if (!preg_match('/^[a-f0-9]{16}$/', $hash)) {
            $r->error('Некорректный hash (16 hex)');
            return $r;
        }
        if (!$force) {
            $r->error('Укажите --force (перемещение в карантин)');
            return $r;
        }

        $contentDir = $this->paths->content($domain, $allias) . '/' . $category;
        $files = glob($contentDir . '/' . $hash . '.*') ?: [];
        if ($files === []) {
            $r->warn("Контент не найден: {$hash}");
            $context->step('Нечего удалять', true);
            return $r;
        }

        $trashRoot = $this->paths->contentRoot() . '/.trash';
        $trashDir = $trashRoot . '/' . $domain . '_' . $allias . '_' . $category . '_' . $hash . '_trash_' . time();
        Fs::mkdir($trashDir);

        foreach ($files as $file) {
            $basename = basename($file);
            if (!@rename($file, $trashDir . '/' . $basename)) {
                $r->error('Не удалось переместить в карантин: ' . $basename);
                return $r;
            }
            $r->file(Fs::rel($trashDir . '/' . $basename, $this->paths->root()));
        }

        $context->step('Контент в карантине', true, Fs::rel($trashDir, $this->paths->root()));
        $r->warn('Очистка: php bin/cli.php trash:clear --force --older=7d');

        return $r;
    }
}
