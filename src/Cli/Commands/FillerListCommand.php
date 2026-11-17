<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Paths;
use Damoony\UI\Region\FillerState;
use RuntimeException;

/**
 * filler:list для схемы domains_app.
 *
 * Источник истины — .blockslocal/{block}/{block}.rules.json (FillerState).
 * Ключа fillers в site.config больше нет.
 */
final class FillerListCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'filler:list';
    }

    public function description(): string
    {
        return 'Список филлеров алиаса (.blockslocal/)';
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

        $localDir = $this->paths->blocksLocal($target->folder(), $target->aliasName());
        if (!is_dir($localDir)) {
            $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'rows' => []];
            $context->step('Филлеров нет', true);

            return $r;
        }

        $state = new FillerState($this->paths);
        $rows = [];

        foreach (glob($localDir . '/*', GLOB_ONLYDIR) ?: [] as $blockDir) {
            $block = basename($blockDir);
            if (str_starts_with($block, '.')) {
                continue;
            }
            if (!is_file($blockDir . '/' . $block . '.php')) {
                continue;
            }

            $enabled = $state->isEnabled($target->folder(), $target->aliasName(), $block);

            $rows[] = [
                'block' => $block,
                'allias' => $target->aliasName(),
                'enabled' => $enabled,
                'has_rules' => is_file($blockDir . '/' . $block . '.rules.json'),
                'has_editor' => is_file($blockDir . '/' . $block . '.editor.json'),
            ];
            $r->file($block . '_' . $target->aliasName());
            $context->step(
                sprintf('%-20s_%s enabled: %s', $block, $target->aliasName(), $enabled ? 'yes' : 'no'),
                true,
                $block,
            );
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['block'], $b['block']));

        $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
