<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * block:list для схемы domains_app.
 */
final class BlockListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'block:list';
    }

    public function description(): string
    {
        return 'Список глобальных блоков проекта (.blocksglobal/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $dir = $this->paths->blocksGlobal($project->folder);
        if (!is_dir($dir)) {
            $r->data = ['project' => $project->key, 'rows' => []];
            $context->step('Блоков нет', true);

            return $r;
        }

        $dirs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);

        $rows = [];
        foreach ($dirs as $blockDir) {
            $name = basename($blockDir);
            if (str_starts_with($name, '.')) {
                continue;
            }

            $type = 'mixed';
            $enabled = false;

            $rulesFile = $blockDir . '/' . $name . '.rules.json';
            if (is_file($rulesFile)) {
                $rules = json_decode((string) @file_get_contents($rulesFile), true);
                if (is_array($rules)) {
                    $type = is_string($rules['type'] ?? null) ? $rules['type'] : 'mixed';
                    $enabled = ($rules['enabled'] ?? false) === true;
                }
            }

            $rows[] = [
                'name' => $name,
                'type' => $type,
                'enabled' => $enabled,
                'has_php' => is_file($blockDir . '/' . $name . '.php'),
                'has_editor' => is_file($blockDir . '/' . $name . '.editor.json'),
            ];
            $r->file($name);
            $context->step(
                sprintf('%-20s type: %-8s enabled: %s', $name, $type, $enabled ? 'yes' : 'no'),
                true,
                $name,
            );
        }

        $r->data = ['project' => $project->key, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
