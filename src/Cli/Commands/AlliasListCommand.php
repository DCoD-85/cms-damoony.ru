<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\UnknownProjectException;

/**
 * allias:list для схемы domains_app.
 */
final class AlliasListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'allias:list';
    }

    public function description(): string
    {
        return 'Список алиасов проекта (domains_app + наличие папки в app/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        if ($domain === '') {
            $r->error('Укажите --domain');

            return $r;
        }

        try {
            $project = $this->site->resolve($domain);
        } catch (UnknownProjectException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $rows = [];
        foreach ($project->aliasNames() as $alias) {
            $settings = $project->alias($alias);
            if ($settings === null) {
                continue;
            }

            $dir = $this->paths->allias($project->folder, $alias);
            $exists = Fs::isDir($dir);

            $rows[] = [
                'allias' => $alias,
                'available' => $settings->available,
                'langs' => $settings->langs,
                'dir_exists' => $exists,
            ];

            $mark = $exists ? '[ok]' : '[missing]';
            $context->step(
                sprintf('%s %-15s langs: %s', $mark, $alias, implode(',', $settings->langs)),
                true,
                $alias,
            );
        }

        $r->data = ['project' => $project->key, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
