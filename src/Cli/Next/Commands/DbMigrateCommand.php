<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\MigrationRunner;
use Throwable;

/**
 * db:migrate — применить SQL-миграции из migrations/.
 *
 * --dir разрешён только внутри migrations/ (realpath + префикс).
 */
final class DbMigrateCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ?Connection $db,
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

        if ($this->db === null) {
            $r->error('БД не сконфигурирована (config/engine.config → db)');

            return $r;
        }

        $defaultDir = $this->paths->root() . '/migrations';
        $dir = (string) ($args['dir'] ?? $defaultDir);

        if ($dir !== $defaultDir) {
            $real = realpath($dir);
            $realDefault = realpath($defaultDir);
            if ($real === false || $realDefault === false || !str_starts_with($real, $realDefault . DIRECTORY_SEPARATOR)) {
                $r->error('Недопустимый --dir (разрешены только подпапки migrations/)');

                return $r;
            }
            $dir = $real;
        }

        if (!is_dir($dir)) {
            $r->error('Каталог миграций не найден: ' . $dir);

            return $r;
        }

        try {
            $applied = new MigrationRunner($this->db, $dir)->run();
        } catch (Throwable $e) {
            $r->error('Миграции не применены: ' . $e->getMessage());

            return $r;
        }

        foreach ($applied as $version) {
            $context->step('Применено', true, $version);
        }

        if ($applied === []) {
            $context->step('Нет новых миграций', true);
        }

        $r->data = ['dir' => $dir, 'applied' => $applied];

        return $r;
    }
}
