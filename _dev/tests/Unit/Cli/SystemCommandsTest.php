<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\BackupCreateCommand;
use Damoony\Cli\Commands\BackupRestoreCommand;
use Damoony\Cli\Commands\SystemCleanCommand;
use Damoony\Cli\Commands\SystemScanCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Support\TrashScanner;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\MaintenanceConfig;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class SystemCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_system_' . bin2hex(random_bytes(6));

        foreach (['bin', 'src', 'vendor', 'www', 'config', 'app', 'var/trash', 'content'] as $dir) {
            mkdir($this->root . '/' . $dir, 0o775, true);
        }

        $writer = SiteConfigWriter::forRoot($this->root);
        $writer->ensureProject('damoony.ru', 'damoony.ru', 'damoony_ru');
        $writer->ensureMirror('damoony.ru', 'damoony.ru');
        $writer->ensureAlias('damoony.ru', 'www');

        mkdir($this->root . '/app/damoony.ru/www', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testScanReportsIdealState(): void
    {
        file_put_contents($this->root . '/composer.json', '{}');

        $result = $this->scan()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['ideal']);
        self::assertSame([], $result->data['garbage_root']);
    }

    public function testScanReportsGarbageAndMissing(): void
    {
        mkdir($this->root . '/old_backup');
        file_put_contents($this->root . '/junk.txt', 'x');
        rmdir($this->root . '/vendor');

        $result = $this->scan()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertFalse($result->data['ideal']);
        self::assertContains('old_backup', $result->data['garbage_root']);
        self::assertContains('junk.txt', $result->data['garbage_root']);
        self::assertSame(['vendor/'], $result->data['missing']);
    }

    public function testScanFlagsUnexpectedWwwEntries(): void
    {
        file_put_contents($this->root . '/www/index.php', '<?php');
        mkdir($this->root . '/www/old');

        $result = $this->scan()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(['old'], $result->data['garbage_www']);
    }

    public function testCleanDryRunKeepsTrash(): void
    {
        $trash = $this->root . '/app/old.ru_trash_' . time();
        mkdir($trash);

        $result = $this->clean()->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertDirectoryExists($trash);
        self::assertSame(0, $result->data['deleted']);
    }

    public function testCleanRemovesTrashAndSessions(): void
    {
        $trash = $this->root . '/app/old.ru_trash_' . time();
        mkdir($trash);
        $session = $this->root . '/app/.sess_abcdef12.damoony.ru';
        mkdir($session);

        $result = $this->clean()->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($trash);
        self::assertDirectoryDoesNotExist($session);
        self::assertSame(2, $result->data['deleted']);
    }

    public function testCleanTrashOnlySkipsSessions(): void
    {
        $trash = $this->root . '/app/old.ru_trash_' . time();
        mkdir($trash);
        $session = $this->root . '/app/.sess_abcdef12.damoony.ru';
        mkdir($session);

        $result = $this->clean()->run(['force' => '1', 'trash' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertDirectoryDoesNotExist($trash);
        self::assertDirectoryExists($session);
    }

    public function testBackupRequiresMysqlDriver(): void
    {
        file_put_contents(
            $this->root . '/config/engine.config',
            json_encode(['db' => ['driver' => 'sqlite', 'path' => 'var/x.db']]),
        );

        $result = $this->backupCreate()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Только MySQL', $result->errors[0]);
    }

    public function testBackupCreateWithoutEngineConfigFails(): void
    {
        $result = $this->backupCreate()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Только MySQL', $result->errors[0]);
    }

    public function testRestoreRequiresFile(): void
    {
        $result = $this->backupRestore()->run(['domain' => 'damoony.ru'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--file', $result->errors[0]);
    }

    public function testRestoreRequiresExistingFile(): void
    {
        $result = $this->backupRestore()->run(
            ['domain' => 'damoony.ru', 'file' => 'nope.zip', 'force' => '1'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('не найден', $result->errors[0]);
    }

    public function testRestoreAcceptsAbsolutePath(): void
    {
        // Абсолютный путь не должен склеиваться с корнем проекта.
        $absolute = sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(4)) . '.zip';

        $result = $this->backupRestore()->run(
            ['domain' => 'damoony.ru', 'file' => $absolute, 'force' => '1'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString($absolute, $result->errors[0]);
        self::assertStringNotContainsString($this->root . '/' . ltrim($absolute, '/\\'), $result->errors[0]);
    }

    public function testRestoreRequiresForce(): void
    {
        file_put_contents($this->root . '/backup.zip', 'not a zip');

        $result = $this->backupRestore()->run(
            ['domain' => 'damoony.ru', 'file' => 'backup.zip'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('--force', $result->errors[0]);
    }

    public function testCleanPrunesLogsAndRateLimitByConfig(): void
    {
        mkdir($this->root . '/var/log/cli', 0o775, true);
        mkdir($this->root . '/var/ratelimit', 0o775, true);
        mkdir($this->root . '/var/form-ratelimit', 0o775, true);

        $old = time() - 40 * 86400;
        $fresh = time();

        file_put_contents($this->root . '/var/log/cli/old.log', '{}\n');
        touch($this->root . '/var/log/cli/old.log', $old);
        file_put_contents($this->root . '/var/log/cli/fresh.log', '{}\n');
        touch($this->root . '/var/log/cli/fresh.log', $fresh);

        file_put_contents($this->root . '/var/ratelimit/old.json', '{}');
        touch($this->root . '/var/ratelimit/old.json', $old);
        file_put_contents($this->root . '/var/form-ratelimit/old.json', '{}');
        touch($this->root . '/var/form-ratelimit/old.json', $old);

        $result = $this->clean([
            'logs' => ['cli_keep_days' => 30],
            'ratelimit' => ['storage_keep_days' => 30],
        ])->run(['force' => '1'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileDoesNotExist($this->root . '/var/log/cli/old.log');
        self::assertFileExists($this->root . '/var/log/cli/fresh.log');
        self::assertFileDoesNotExist($this->root . '/var/ratelimit/old.json');
        self::assertFileDoesNotExist($this->root . '/var/form-ratelimit/old.json');
        self::assertSame(1, $result->data['logs_deleted']);
        self::assertSame(2, $result->data['ratelimit_deleted']);
    }
    private function scan(): SystemScanCommand
    {
        return new SystemScanCommand(new Paths($this->root));
    }

    /** @param array<string, mixed> $config */
    private function clean(array $config = []): SystemCleanCommand
    {
        $paths = new Paths($this->root);

        return new SystemCleanCommand($paths, new TrashScanner($paths), MaintenanceConfig::fromArray($config));
    }

    private function site(): SiteConfig
    {
        return SiteConfig::fromFile($this->root . '/config/site.config');
    }

    private function backupCreate(): BackupCreateCommand
    {
        return new BackupCreateCommand(new Paths($this->root), $this->site(), MaintenanceConfig::defaults());
    }

    private function backupRestore(): BackupRestoreCommand
    {
        return new BackupRestoreCommand(new Paths($this->root), $this->site());
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
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
