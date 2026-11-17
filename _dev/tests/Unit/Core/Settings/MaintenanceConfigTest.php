<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Settings;

use Damoony\Core\Settings\MaintenanceConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MaintenanceConfigTest extends TestCase
{
    public function testDefaultsWhenFileMissing(): void
    {
        $config = MaintenanceConfig::fromFile(__DIR__ . '/does-not-exist.config');

        self::assertSame(7, $config->backupsKeep());
        self::assertSame(['app', 'content', 'media', 'db'], $config->backupParts());
        self::assertFalse($config->compressMedia());
        self::assertSame(0, $config->trashTtlDays());
        self::assertSame(24, $config->sessionsTtlHours());
        self::assertSame(30, $config->contentGcTtlDays());
        self::assertSame(25 * 1024 * 1024, $config->contentMaxBytes());
        self::assertSame(200, $config->contentMinSidePx());
        self::assertSame(14, $config->cliLogsKeepDays());
        self::assertSame(7, $config->ratelimitKeepDays());
    }

    public function testCronDefaults(): void
    {
        $config = MaintenanceConfig::defaults();

        self::assertSame('var/locks', $config->cronLockDir());
        self::assertSame([], $config->cronBackupDomains());
        self::assertSame([
            'cleanup' => true,
            'content_gc' => false,
            'backup' => true,
            'structure' => true,
            'health' => true,
        ], $config->cronTasks());
    }

    public function testCronOverrides(): void
    {
        $config = MaintenanceConfig::fromArray([
            'cron' => [
                'lock_dir' => 'var/my-locks',
                'tasks' => ['content_gc' => true, 'backup' => false],
                'backup_domains' => ['damoony.ru'],
            ],
        ]);

        self::assertSame('var/my-locks', $config->cronLockDir());
        self::assertTrue($config->cronTasks()['content_gc']);
        self::assertFalse($config->cronTasks()['backup']);
        self::assertTrue($config->cronTasks()['cleanup']);
        self::assertSame(['damoony.ru'], $config->cronBackupDomains());
    }

    public function testCronRejectsUnknownTask(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('неизвестная cron-задача');
        MaintenanceConfig::fromArray(['cron' => ['tasks' => ['ghost' => true]]]);
    }
    public function testOverridesMergeWithDefaults(): void
    {
        $config = MaintenanceConfig::fromArray([
            'backups' => ['keep' => 3, 'compress_media' => true],
            'trash' => ['ttl_days' => 14],
            'content' => ['max_mb' => 5],
        ]);

        self::assertSame(3, $config->backupsKeep());
        self::assertTrue($config->compressMedia());
        // Незаданное берётся из дефолтов.
        self::assertSame(['app', 'content', 'media', 'db'], $config->backupParts());
        self::assertSame(14, $config->trashTtlDays());
        self::assertSame(5 * 1024 * 1024, $config->contentMaxBytes());
        self::assertSame(200, $config->contentMinSidePx());
    }

    public function testBackupPartsAreCanonicalAndValidated(): void
    {
        $config = MaintenanceConfig::fromArray(['backups' => ['parts' => ['db', 'app']]]);
        self::assertSame(['app', 'db'], $config->backupParts());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('допустимо');
        MaintenanceConfig::fromArray(['backups' => ['parts' => ['app', 'logs']]]);
    }

    public function testRejectsNegativeInt(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('целым ≥ 0');
        MaintenanceConfig::fromArray(['backups' => ['keep' => -1]]);
    }

    public function testRejectsWrongBoolType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boolean');
        MaintenanceConfig::fromArray(['backups' => ['compress_media' => 'yes']]);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('некорректный JSON');
        MaintenanceConfig::fromJson('{not json');
    }
}
