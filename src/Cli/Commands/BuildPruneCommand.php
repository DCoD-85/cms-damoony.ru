<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Data\Persona\PersonaRepository;

/**
 * build:prune — удаляет просроченные персональные сборки (файлы + записи).
 */
final class BuildPruneCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly PersonaRepository $persona,
    ) {
    }

    public function name(): string
    {
        return 'build:prune';
    }

    public function description(): string
    {
        return 'Удалить просроченные сборки установок (по TTL)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $expired = $this->persona->expiredBuilds();
        $deleted = 0;

        foreach ($expired as $build) {
            $file = $this->paths->root() . '/var/builds/' . basename((string) $build['file']);
            if (is_file($file)) {
                @unlink($file);
            }
            $this->persona->deleteBuild((int) $build['id']);
            $deleted++;
        }

        $r->data = ['deleted' => $deleted];
        $context->step('Удалено просроченных сборок: ' . $deleted, true);

        return $r;
    }
}
