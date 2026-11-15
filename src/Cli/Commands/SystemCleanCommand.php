<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class SystemCleanCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'system:clean';
    }

    public function description(): string
    {
        return 'Очистка сервера: удаление корзин, старых сессий и тестового мусора';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $force = isset($args['force']) && $args['force'] !== '0' && $args['force'] !== 'false';
        $onlySessions = isset($args['sessions']);
        $onlyTrash = isset($args['trash']);

        $root = $this->paths->root();
        $targets = [];

        // 1. Корзины (trash)
        if (!$onlySessions) {
            $targets = array_merge($targets, glob($root . '/app/*_trash_*') ?: []);
            $targets = array_merge($targets, glob($root . '/app/*/*_trash_*') ?: []);
            $targets = array_merge($targets, glob($root . '/www/media/*_trash_*') ?: []);
            $targets = array_merge($targets, glob($root . '/www/media/*/*_trash_*') ?: []);
            $targets = array_merge($targets, glob($root . '/content/.trash') ?: []);
        }

        // 2. Сессии (sessions)
        if (!$onlyTrash) {
            $targets = array_merge($targets, glob($root . '/app/.sess_*') ?: []);
            $targets = array_merge($targets, glob($root . '/www/media/.sess_*') ?: []);
            $targets = array_merge($targets, glob($root . '/var/wizard/*') ?: []);
        }

        // Убираем дубликаты и несуществующие пути
        $targets = array_filter(array_unique($targets), 'is_dir');

        if (empty($targets)) {
            $context->step('Мусора не найдено. Сервер чист.', true);
            return $r;
        }

        if (!$force) {
            $context->step('Найдено ' . count($targets) . ' директорий для удаления:', true);
            foreach ($targets as $t) {
                $context->step('  - ' . $t, true);
            }
            $context->step('Для реального удаления запустите с флагом --force', true);
            return $r;
        }

        $deleted = 0;
        foreach ($targets as $path) {
            if (Fs::rmrf($path)) {
                $deleted++;
            } else {
                $context->step('Ошибка удаления: ' . $path, false);
            }
        }

        $context->step("Успешно удалено директорий: {$deleted}", true);
        return $r;
    }
}
