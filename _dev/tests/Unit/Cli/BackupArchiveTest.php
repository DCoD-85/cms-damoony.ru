<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Support\BackupArchive;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupArchiveTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/damoony_archive_' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    public function testParsePartsDefaultsToAll(): void
    {
        self::assertSame(['app', 'content', 'media', 'db'], new BackupArchive()->parseParts(''));
    }

    public function testParsePartsKeepsCanonicalOrder(): void
    {
        self::assertSame(['app', 'db'], new BackupArchive()->parseParts('db,app'));
    }

    public function testParsePartsRejectsUnknown(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Неизвестная часть бэкапа "logs"');
        new BackupArchive()->parseParts('app,logs');
    }

    public function testPackWritesManifestWithStats(): void
    {
        $staging = $this->tmp . '/staging';
        mkdir($staging . '/app/damoony.ru', 0o775, true);
        mkdir($staging . '/content/damoony.ru/www/images', 0o775, true);
        file_put_contents($staging . '/app/damoony.ru/algoritm.php', '<?php // page');
        file_put_contents($staging . '/content/damoony.ru/www/images/a.svg', '<svg/>');
        file_put_contents($staging . '/db.sql', 'CREATE TABLE t (id int);');

        $zipPath = $this->tmp . '/backup_test.zip';
        $archive = new BackupArchive();

        $manifest = $archive->pack($staging, $zipPath, ['app', 'content', 'db'], [
            'project' => 'damoony.ru',
            'folder' => 'damoony.ru',
        ]);

        self::assertFileExists($zipPath);
        self::assertSame('damoony.ru', $manifest['project']);
        self::assertSame(1, $manifest['parts']['app']['files']);
        self::assertSame(1, $manifest['parts']['content']['files']);
        self::assertSame(1, $manifest['parts']['db']['files']);
        self::assertSame(0, $manifest['parts']['media']['files']);
        self::assertNotSame('', $manifest['created_at']);

        // Манифест реально лежит внутри архива и читается обратно.
        $read = $archive->readManifestOfArchive($zipPath);
        self::assertIsArray($read);
        self::assertSame($manifest['created_at'], $read['created_at']);
        self::assertSame(1, $read['parts']['app']['files']);
        self::assertSame('damoony.ru', $read['project']);
        self::assertSame('damoony.ru', $read['folder']);
    }

    public function testPruneKeepsNewestAndDeletesRest(): void
    {
        $dir = $this->tmp . '/backups';
        mkdir($dir, 0o775, true);

        foreach (['2026-11-01_10-00-00', '2026-11-02_10-00-00', '2026-11-03_10-00-00'] as $stamp) {
            file_put_contents($dir . '/backup_damoony.ru_' . $stamp . '.zip', 'x');
        }
        file_put_contents($dir . '/backup_other.ru_2026-11-04_10-00-00.zip', 'x');

        $deleted = new BackupArchive()->prune($dir, 'backup_damoony.ru_', 2);

        self::assertCount(1, $deleted);
        self::assertFileDoesNotExist($dir . '/backup_damoony.ru_2026-11-01_10-00-00.zip');
        self::assertFileExists($dir . '/backup_damoony.ru_2026-11-02_10-00-00.zip');
        self::assertFileExists($dir . '/backup_damoony.ru_2026-11-03_10-00-00.zip');
        // Чужой проект не трогаем.
        self::assertFileExists($dir . '/backup_other.ru_2026-11-04_10-00-00.zip');
    }

    public function testPruneIgnoresZeroKeep(): void
    {
        $dir = $this->tmp . '/backups';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/backup_damoony.ru_2026-11-01_10-00-00.zip', 'x');

        self::assertSame([], new BackupArchive()->prune($dir, 'backup_damoony.ru_', 0));
        self::assertFileExists($dir . '/backup_damoony.ru_2026-11-01_10-00-00.zip');
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
