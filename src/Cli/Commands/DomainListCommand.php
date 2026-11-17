<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;

/**
 * domain:list для схемы domains_app.
 */
final class DomainListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'domain:list';
    }

    public function description(): string
    {
        return 'Список проектов domains_app + наличие папки в app/';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        if ($this->site->projectKeys() === []) {
            $r->data = ['rows' => []];
            $context->step('Проектов нет', true);

            return $r;
        }

        $rows = [];
        foreach ($this->site->projects() as $key => $project) {
            $dir = $this->paths->domain($project->folder);
            $exists = Fs::isDir($dir);

            $context->debug("Checking project: {$key} -> {$dir}");

            $mirrors = [];
            foreach ($project->mirrors() as $host => $available) {
                $mirrors[] = ['host' => $host, 'available' => $available];
            }

            $aliases = $project->aliasNames();
            sort($aliases);

            $rows[] = [
                'project' => $key,
                'folder' => $project->folder,
                'namespace' => $project->namespace,
                'mirrors' => $mirrors,
                'aliases' => $aliases,
                'dir_exists' => $exists,
            ];
            $r->file($key);

            $mark = $exists ? '[ok]' : '[missing]';
            $context->step(
                sprintf('%s %-20s app/%s, mirrors: %d, aliases: %d', $mark, $key, $project->folder, count($mirrors), count($aliases)),
                true,
                $key,
            );
        }

        $r->data = ['rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
