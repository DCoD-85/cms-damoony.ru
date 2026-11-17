<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Support\TrashScanner;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class TrashScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_trash_scan_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/app', 0o775, true);
        mkdir($this->root . '/www/media/.trash', 0o775, true);
        mkdir($this->root . '/content/.trash', 0o775, true);
        mkdir($this->root . '/var/trash', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testFindsEpochQuarantine(): void
    {
        $dir = $this->root . '/app/damoony.ru_trash_1794735431';
        mkdir($dir, 0o775, true);

        $found = new TrashScanner(new Paths($this->root))->find();

        self::assertCount(1, $found);
        self::assertSame($dir, $found[0]['path']);
        self::assertSame(1794735431, $found[0]['ts']);
        self::assertSame('domain', $found[0]['kind']);
    }

    public function testFindsRestoreQuarantineWithHumanDate(): void
    {
        // backup:restore создаёт карантин с человекочитаемой меткой.
        $dir = $this->root . '/app/damoony.ru_trash_restore_2026-11-17_19-28-12';
        mkdir($dir, 0o775, true);

        $found = new TrashScanner(new Paths($this->root))->find();

        self::assertCount(1, $found);
        self::assertSame($dir, $found[0]['path']);
        self::assertSame('domain', $found[0]['kind']);
        self::assertSame(strtotime('2026-11-17 19:28:12'), $found[0]['ts']);
    }

    public function testIgnoresDirectoriesWithoutTrashMarker(): void
    {
        mkdir($this->root . '/app/damoony.ru');                       // рабочий проект
        mkdir($this->root . '/app/not_a_marker_2026-11-17_19-28-12'); // нет _trash_
        mkdir($this->root . '/app/damoony.ru_trash_marker');          // маркер есть, метки нет

        self::assertSame([], new TrashScanner(new Paths($this->root))->find());
    }

    public function testDomainFilterMatchesRestoreQuarantine(): void
    {
        mkdir($this->root . '/app/damoony.ru_trash_restore_2026-11-17_19-28-12', 0o775, true);
        mkdir($this->root . '/app/other.ru_trash_1794735431', 0o775, true);

        $found = new TrashScanner(new Paths($this->root))->find('damoony.ru');

        self::assertCount(1, $found);
        self::assertStringContainsString('damoony.ru_trash_restore_', $found[0]['path']);
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
