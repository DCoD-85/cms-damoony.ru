<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\AlertNotifyCommand;
use Damoony\Cli\Commands\CronRunCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\MaintenanceConfig;
use Damoony\Core\Settings\ObservabilityConfig;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class CronRunTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cron_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/var', 0o775, true);

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testRunsEnabledTasksAndWritesJournal(): void
    {
        $calls = [];
        $command = $this->cron(
            ['cleanup' => true, 'health' => true],
            [
                'cleanup' => $this->okTask('cleanup', $calls),
                'health' => $this->okTask('health', $calls),
                'backup' => $this->okTask('backup', $calls),
            ],
        );

        $result = $command->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(['cleanup', 'health'], $calls);
        self::assertSame(0, $result->data['failed']);

        $journal = $this->journal();
        self::assertCount(3, $journal); // 2 задачи + _cron
        self::assertSame('_cron', $journal[2]['task']);
        self::assertTrue($journal[2]['ok']);
    }

    public function testFailingTaskTriggersAlertAndExitError(): void
    {
        $calls = [];
        $command = $this->cron(
            ['cleanup' => true],
            ['cleanup' => $this->failTask('boom', $calls)],
        );

        $result = $command->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertSame(1, $result->data['failed']);

        $alerts = $this->alerts();
        self::assertCount(1, $alerts);
        self::assertSame('error', $alerts[0]['level']);
        self::assertSame('cron', $alerts[0]['source']);
        self::assertStringContainsString('boom', $alerts[0]['message']);
    }

    public function testDisabledTasksAreSkipped(): void
    {
        $calls = [];
        $command = $this->cron(
            ['cleanup' => false, 'health' => true],
            [
                'cleanup' => $this->okTask('cleanup', $calls),
                'health' => $this->okTask('health', $calls),
            ],
        );

        $result = $command->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(['health'], $calls);
    }

    public function testSelectedTaskOnly(): void
    {
        $calls = [];
        $command = $this->cron(
            ['cleanup' => true, 'health' => true],
            [
                'cleanup' => $this->okTask('cleanup', $calls),
                'health' => $this->okTask('health', $calls),
            ],
        );

        $result = $command->run(['task' => 'health'], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(['health'], $calls);
    }

    public function testDryRunDoesNotExecute(): void
    {
        $calls = [];
        $command = $this->cron(['cleanup' => true], ['cleanup' => $this->okTask('cleanup', $calls)]);

        $result = $command->run([], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertSame([], $calls);
        self::assertTrue($result->data['dry_run']);
    }

    public function testSkipsWhenLocked(): void
    {
        $lockDir = $this->root . '/var/locks';
        Fs::mkdir($lockDir);
        $handle = fopen($lockDir . '/cron.lock', 'c');
        self::assertIsResource($handle);
        self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        $calls = [];
        $command = $this->cron(['cleanup' => true], ['cleanup' => $this->okTask('cleanup', $calls)]);

        $result = $command->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertSame([], $calls);
        self::assertTrue($result->data['skipped']);
        self::assertNotEmpty($result->warnings);

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function testUnknownTaskRejected(): void
    {
        $calls = [];
        $command = $this->cron(['cleanup' => true], ['cleanup' => $this->okTask('cleanup', $calls)]);

        $result = $command->run(['task' => 'ghost'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Неизвестная задача', $result->errors[0]);
    }

    /**
     * @param array<string, bool> $tasks
     * @param array<string, CommandInterface> $commands
     */
    private function cron(array $tasks, array $commands): CronRunCommand
    {
        $observability = ObservabilityConfig::defaults();

        // Явно выключаем всё, что не просили: MaintenanceConfig мержит с дефолтами.
        $tasks = array_merge([
            'cleanup' => false,
            'content_gc' => false,
            'backup' => false,
            'structure' => false,
            'health' => false,
        ], $tasks);

        return new CronRunCommand(
            new Paths($this->root),
            SiteConfig::fromFile($this->root . '/config/site.config'),
            MaintenanceConfig::fromArray(['cron' => ['tasks' => $tasks]]),
            new AlertNotifyCommand(new Paths($this->root), $observability),
            $commands,
        );
    }

    /**
     * @param list<string> $calls
     */
    private function okTask(string $name, array &$calls): CommandInterface
    {
        return new class ($name, $calls) implements CommandInterface {
            /** @param list<string> $calls */
            public function __construct(private readonly string $taskName, private array &$calls)
            {
            }

            public function name(): string
            {
                return $this->taskName;
            }

            public function description(): string
            {
                return 'stub';
            }

            public function run(array $args, Context $context): Result
            {
                $this->calls[] = $this->taskName;

                return new Result();
            }
        };
    }

    /**
     * @param list<string> $calls
     */
    private function failTask(string $error, array &$calls): CommandInterface
    {
        return new class ($error, $calls) implements CommandInterface {
            /** @param list<string> $calls */
            public function __construct(private readonly string $error, private array &$calls)
            {
            }

            public function name(): string
            {
                return 'failing';
            }

            public function description(): string
            {
                return 'stub';
            }

            public function run(array $args, Context $context): Result
            {
                $this->calls[] = 'failing';
                $r = new Result();
                $r->error($this->error);

                return $r;
            }
        };
    }

    /** @return list<array<string, mixed>> */
    private function journal(): array
    {
        $files = glob($this->root . '/var/log/cron-*.jsonl') ?: [];
        if ($files === []) {
            return [];
        }

        $rows = [];
        foreach (file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function alerts(): array
    {
        $file = $this->root . '/var/alerts.log';
        if (!is_file($file)) {
            return [];
        }

        $rows = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function dryRunContext(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        }, false, true);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
