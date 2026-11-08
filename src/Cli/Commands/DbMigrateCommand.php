<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\MigrationRunner;

final class DbMigrateCommand implements CommandInterface
{
    /** @param array<string, mixed> $dbConfig */
    public function __construct(
        private readonly Paths $paths,
        private readonly array $dbConfig = ['driver' => 'sqlite', 'path' => ':memory:'],
        private readonly ?Connection $connection = null,
    ) {
    }

    public function name(): string
    {
        return 'db:migrate';
    }

    public function description(): string
    {
        return 'Применить SQL-миграции из migrations/';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $defaultDir = $this->paths->root() . '/migrations';
        $dir = (string) ($args['dir'] ?? $defaultDir);

        // Path traversal guard: разрешён только migrations/ внутри проекта
        if ($dir !== $defaultDir) {
            $real = realpath($dir);
            $realDefault = realpath($defaultDir);
            if ($real === false || $realDefault === false || !str_starts_with($real, $realDefault . '/')) {
                $r->error('Недопустимый --dir (разрешены только подпапки migrations/)');
                return $r;
            }
            $dir = $real;
        }

        if (!is_dir($dir)) {
            $r->error('Каталог миграций не найден: ' . $dir);
            return $r;
        }

        $connection = $this->connection ?? new Connection($this->dbConfig);
        $runner = new MigrationRunner($connection, $dir);

        try {
            $applied = $runner->run();
        } catch (\Throwable $e) {
            $r->error($e->getMessage());
            return $r;
        }

        if ($applied === []) {
            $context->step('Нет новых миграций', true);
        } else {
            foreach ($applied as $v) {
                $context->step('Применено', true, $v);
            }
        }

        return $r;
    }
}
