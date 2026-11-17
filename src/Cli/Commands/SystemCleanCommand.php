<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\TrashScanner;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\MaintenanceConfig;

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
        private readonly MaintenanceConfig $maintenance,
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
            if ($force) {
                $this->pruneLogs($r, $context);
                $this->pruneRateLimit($r, $context);
            }
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

        $this->pruneLogs($r, $context);
        $this->pruneRateLimit($r, $context);

        return $r;
    }

    /**
     * Ротация журнала CLI по `logs.cli_keep_days`.
     */
    private function pruneLogs(Result $r, Context $context): void
    {
        $days = $this->maintenance->cliLogsKeepDays();
        if ($days <= 0) {
            return;
        }

        $dir = $this->paths->root() . '/var/log/cli';
        if (!is_dir($dir)) {
            return;
        }

        $threshold = time() - $days * 86400;
        $deleted = 0;
        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.log')) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path) && (int) filemtime($path) < $threshold && @unlink($path)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $context->step(sprintf('Удалено старых журналов CLI: %d (старше %d дн.)', $deleted, $days), true);
            $r->data['logs_deleted'] = $deleted;
        }
    }

    /**
     * Чистка хранилищ rate-limit по `ratelimit.storage_keep_days`.
     */
    private function pruneRateLimit(Result $r, Context $context): void
    {
        $days = $this->maintenance->ratelimitKeepDays();
        if ($days <= 0) {
            return;
        }

        $threshold = time() - $days * 86400;
        $deleted = 0;

        foreach (['var/ratelimit', 'var/form-ratelimit'] as $relative) {
            $dir = $this->paths->root() . '/' . $relative;
            if (!is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . '/' . $entry;
                if (is_file($path) && (int) filemtime($path) < $threshold && @unlink($path)) {
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $context->step(sprintf('Удалено старых записей rate-limit: %d (старше %d дн.)', $deleted, $days), true);
            $r->data['ratelimit_deleted'] = $deleted;
        }
    }
}
