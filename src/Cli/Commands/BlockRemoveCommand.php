<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

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
        return 'Удалить блок alliasglobal (в карантин)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя блока');
            return $r;
        }
        if (!$force) {
            $r->error('Для удаления укажите --force (папка будет перемещена в карантин)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден');
            return $r;
        }
        $realDomain = (string) $site['domains_real'][$domain];

        $blockDir = $this->paths->domain($realDomain) . '/.alliasglobal/' . $name;
        if (!is_dir($blockDir)) {
            $r->warn("Блок не найден: {$name}");
            $context->step('Нечего удалять', true);
            return $r;
        }

        $trash = $blockDir . '_trash_' . time();
        if (!@rename($blockDir, $trash)) {
            $r->error('Не удалось переместить в карантин');
            return $r;
        }

        $context->step('Блок перемещён в карантин', true, basename($trash));
        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');

        return $r;
    }
}
