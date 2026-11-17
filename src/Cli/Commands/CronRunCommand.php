<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\MaintenanceConfig;
use Damoony\Core\Settings\SiteConfig;
use Throwable;

/**
 * cron:run — один запуск обслуживания: задачи из maintenance.config.cron.tasks.
 *
 * Гарантии: flock (два запуска не накладываются), журнал `var/log/cron-*.jsonl`,
 * алерт при провале задачи (с дедупом), exit 1 если хоть одна задача упала.
 */
final class CronRunCommand implements CommandInterface
{
    /**
     * @param array<string, CommandInterface> $tasks
     */
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly MaintenanceConfig $maintenance,
        private readonly AlertNotifyCommand $alerts,
        private readonly array $tasks,
    ) {
    }

    public function name(): string
    {
        return 'cron:run';
    }

    public function description(): string
    {
        return 'Выполнить обслуживание по maintenance.config.cron.tasks (--task=, --dry-run)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $selected = (string) ($args['task'] ?? '');
        $dry = $context->isDryRun();

        $enabled = array_filter($this->maintenance->cronTasks(), static fn (bool $on): bool => $on);

        if ($selected !== '') {
            if (!isset($this->tasks[$selected])) {
                $r->error('Неизвестная задача: ' . $selected . '. Есть: ' . implode(', ', array_keys($this->tasks)));

                return $r;
            }
            $enabled = [$selected => true];
        }

        if ($enabled === []) {
            $context->step('Нет включённых cron-задач (maintenance.config.cron.tasks)');
            $r->data = ['tasks' => [], 'failed' => 0];

            return $r;
        }

        if ($dry) {
            foreach (array_keys($enabled) as $name) {
                $context->step('[dry] ' . $name);
            }
            $r->data = ['tasks' => array_keys($enabled), 'dry_run' => true, 'failed' => 0];

            return $r;
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            $r->warn('cron:run уже выполняется — запуск пропущен');
            $r->data = ['skipped' => true, 'tasks' => []];

            return $r;
        }

        $failed = 0;
        $report = [];
        $started = microtime(true);

        try {
            foreach (array_keys($enabled) as $name) {
                if (!isset($this->tasks[$name])) {
                    $r->warn('Задача ' . $name . ' включена в конфиге, но недоступна в этой сборке');

                    continue;
                }

                $runs = $this->runsFor($name);

                foreach ($runs as $runArgs) {
                    $domain = (string) ($runArgs['domain'] ?? '');
                    $taskContext = new Context(static function (string $m, bool $ok = true, string $d = ''): void {
                    });

                    $taskStart = microtime(true);
                    $result = $this->tasks[$name]->run($runArgs, $taskContext);
                    $duration = round((microtime(true) - $taskStart) * 1000, 1);

                    $entry = [
                        'task' => $name,
                        'domain' => $domain,
                        'ok' => $result->ok,
                        'duration_ms' => $duration,
                        'errors' => $result->errors,
                    ];
                    $report[] = $entry;
                    $this->journal($entry);

                    if ($result->ok) {
                        $context->step(sprintf('✓ %s%s (%.0f ms)', $name, $domain !== '' ? ':' . $domain : '', $duration), true, $name);
                    } else {
                        $failed++;
                        $context->step(sprintf('✗ %s%s: %s', $name, $domain !== '' ? ':' . $domain : '', $result->errors[0] ?? 'ошибка'), false, $name);
                        $r->error($name . ($domain !== '' ? ':' . $domain : '') . ': ' . ($result->errors[0] ?? 'ошибка'));
                        $this->notifyFailure($name, $domain, $result);
                    }
                }
            }
        } finally {
            $this->releaseLock($lock);
        }

        $total = round((microtime(true) - $started) * 1000, 1);
        $this->journal(['task' => '_cron', 'ok' => $failed === 0, 'failed' => $failed, 'duration_ms' => $total]);

        $r->data = [
            'tasks' => $report,
            'failed' => $failed,
            'duration_ms' => $total,
        ];

        $context->step(sprintf('cron:run завершён: задач %d, провалов %d (%.0f ms)', count($report), $failed, $total), $failed === 0);

        return $r;
    }

    /**
     * Для backup задача выполняется по каждому домену.
     *
     * @return list<array<string, string>>
     */
    private function runsFor(string $name): array
    {
        if ($name !== 'backup') {
            return [match ($name) {
                'cleanup' => ['force' => '1'],
                'content_gc' => ['force' => '1'],
                default => [],
            }];
        }

        $domains = $this->maintenance->cronBackupDomains();
        if ($domains === []) {
            $domains = $this->site->projectKeys();
        }

        return array_map(static fn (string $domain): array => ['domain' => $domain], $domains);
    }

    private function notifyFailure(string $task, string $domain, Result $result): void
    {
        $message = 'cron ' . $task . ($domain !== '' ? ':' . $domain : '') . ' — ' . ($result->errors[0] ?? 'ошибка');

        $this->alerts->run(
            ['message' => $message, 'level' => 'error', 'source' => 'cron'],
            new Context(static function (): void {
            }),
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function journal(array $entry): void
    {
        try {
            $dir = $this->paths->root() . '/var/log';
            Fs::mkdir($dir);
            $line = json_encode(
                ['ts' => gmdate('Y-m-d\TH:i:s\Z')] + $entry,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            if ($line !== false) {
                @file_put_contents($dir . '/cron-' . gmdate('Y-m-d') . '.jsonl', $line . "\n", FILE_APPEND);
            }
        } catch (Throwable) {
            // журнал не должен ломать запуск
        }
    }

    /** @return array{0: resource, 1: string}|null */
    private function acquireLock(): ?array
    {
        $dir = $this->resolvePath($this->maintenance->cronLockDir());
        $file = $dir . '/cron.lock';

        try {
            Fs::mkdir($dir);
        } catch (Throwable) {
            return null;
        }

        $handle = @fopen($file, 'c');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return [$handle, $file];
    }

    /**
     * @param array{0: resource, 1: string} $lock
     */
    private function releaseLock(array $lock): void
    {
        flock($lock[0], LOCK_UN);
        fclose($lock[0]);
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $this->paths->root() . '/' . ltrim($path, '/\\');
    }
}
