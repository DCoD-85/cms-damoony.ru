<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * scheme:list для схемы domains_app.
 */
final class SchemeListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'scheme:list';
    }

    public function description(): string
    {
        return 'Список схем проекта (.schemesglobal/)';
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

        $dir = $this->paths->schemesGlobal($project->folder);
        if (!is_dir($dir)) {
            $r->data = ['project' => $project->key, 'rows' => []];
            $context->step('Схем нет', true);

            return $r;
        }

        $rows = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $rulesFile = $dir . '/' . $name . '.rules.json';
            $editorFile = $dir . '/' . $name . '.editor.json';

            $enabled = false;
            if (is_file($rulesFile)) {
                $rules = json_decode((string) @file_get_contents($rulesFile), true);
                $enabled = is_array($rules) && ($rules['enabled'] ?? false) === true;
            }

            $hasEditor = is_file($editorFile);
            $rows[] = [
                'name' => $name,
                'enabled' => $enabled,
                'has_rules' => is_file($rulesFile),
                'has_editor' => $hasEditor,
                'is_default' => $name === $project->scheme,
            ];
            $r->file($name);

            $context->step(
                sprintf(
                    '%-20s enabled: %s, editor: %s%s',
                    $name,
                    $enabled ? 'yes' : 'no',
                    $hasEditor ? 'yes' : 'no',
                    $name === $project->scheme ? ', default' : '',
                ),
                true,
                $name,
            );
        }

        $r->data = ['project' => $project->key, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
