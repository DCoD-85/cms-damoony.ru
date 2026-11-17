<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\TrashScanner;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * system:clean — уборка карантина и брошенных сессий/визардов.
 *
 * Без --force только показывает план.
 */
final class SystemCleanCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TrashScanner $scanner,
    ) {
    }

    public function name(): string
    {
        return 'system:clean';
    }

    public function description(): string
    {
        return 'Убрать карантин и брошенные сессии (--force для удаления)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $force = isset($args['force']) && !in_array((string) $args['force'], ['0', 'false'], true);
        $onlySessions = isset($args['sessions']);
        $onlyTrash = isset($args['trash']);

        $targets = [];

        if (!$onlySessions) {
            foreach ($this->scanner->find() as $item) {
                $targets[] = $item['path'];
            }
            $targets = array_merge($targets, glob($this->paths->contentRoot() . '/.trash') ?: []);
        }

        if (!$onlyTrash) {
            $targets = array_merge($targets, glob($this->paths->appRoot() . '/.sess_*', GLOB_ONLYDIR) ?: []);
            $targets = array_merge($targets, glob($this->paths->mediaRoot() . '/.sess_*', GLOB_ONLYDIR) ?: []);
            $targets = array_merge($targets, glob($this->paths->root() . '/var/wizard/*', GLOB_ONLYDIR) ?: []);
        }

        $targets = array_values(array_unique(array_filter($targets, 'is_dir')));

        if ($targets === []) {
            $r->data = ['force' => $force, 'targets' => [], 'deleted' => 0];
            $context->step('Мусора не найдено. Сервер чист.', true);

            return $r;
        }

        if (!$force) {
            foreach ($targets as $target) {
                $context->step('[dry] ' . Fs::rel($target, $this->paths->root()));
            }
            $context->step(sprintf('Найдено %d. Запустите с --force для удаления', count($targets)), true);

            $r->data = ['force' => false, 'targets' => $targets, 'deleted' => 0];

            return $r;
        }

        $deleted = 0;
        foreach ($targets as $target) {
            if (Fs::rmrf($target)) {
                $deleted++;
                $r->file(Fs::rel($target, $this->paths->root()));
            } else {
                $r->error('Ошибка удаления: ' . Fs::rel($target, $this->paths->root()));
            }
        }

        $r->data = ['force' => true, 'targets' => $targets, 'deleted' => $deleted];
        $context->step('Удалено директорий: ' . $deleted, true);

        return $r;
    }
}
