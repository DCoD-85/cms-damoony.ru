<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * skin:list для схемы domains_app.
 */
final class SkinListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'skin:list';
    }

    public function description(): string
    {
        return 'Список скинов алиаса (www/media/<folder>/<allias>/skin/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $skinRoot = $this->paths->media($target->folder(), $target->aliasName()) . '/skin';
        if (!is_dir($skinRoot)) {
            $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'rows' => []];
            $context->step('Скинов нет', true);

            return $r;
        }

        $rows = [];
        foreach (glob($skinRoot . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (str_starts_with($name, '.')) {
                continue;
            }

            $hasCss = is_file($dir . '/css/main.css');
            $hasJs = is_file($dir . '/js/main.js');

            $rows[] = [
                'name' => $name,
                'has_css' => $hasCss,
                'has_js' => $hasJs,
                'has_meta' => is_file($dir . '/skin.json'),
            ];
            $r->file($name);
            $context->step(
                sprintf('%-15s css:%s js:%s', $name, $hasCss ? 'y' : 'n', $hasJs ? 'y' : 'n'),
                true,
                $name,
            );
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
