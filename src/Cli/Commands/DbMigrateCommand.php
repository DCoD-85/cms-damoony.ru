<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Db\Connection;
use Damoony\Db\MigrationRunner;
use Damoony\Files\Paths;

final class DbMigrateCommand implements CommandInterface
{
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

        $dir = $args['dir'] ?? ($this->paths->root() . '/migrations');
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