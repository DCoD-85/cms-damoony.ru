<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Files;

use Damoony\Core\Files\Fs;
use PHPUnit\Framework\TestCase;

final class FsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_fs_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o775, true);
    }

    protected function tearDown(): void
    {
        Fs::rmrf($this->root);
    }

    public function testRmrfRemovesFile(): void
    {
        $file = $this->root . '/a.txt';
        file_put_contents($file, 'data');

        self::assertTrue(Fs::rmrf($file));
        self::assertFileDoesNotExist($file);
    }

    public function testRmrfRemovesEmptyDir(): void
    {
        $dir = $this->root . '/empty';
        mkdir($dir);

        self::assertTrue(Fs::rmrf($dir));
        self::assertDirectoryDoesNotExist($dir);
    }

    public function testRmrfRemovesDirWithFiles(): void
    {
        $dir = $this->root . '/withfiles';
        mkdir($dir);
        file_put_contents($dir . '/a.txt', 'A');
        file_put_contents($dir . '/b.txt', 'B');

        self::assertTrue(Fs::rmrf($dir));
        self::assertDirectoryDoesNotExist($dir);
    }

    public function testRmrfRemovesNested(): void
    {
        $dir = $this->root . '/nested';
        mkdir($dir . '/sub1/sub2', 0o775, true);
        file_put_contents($dir . '/root.txt', 'r');
        file_put_contents($dir . '/sub1/a.txt', 'a');
        file_put_contents($dir . '/sub1/sub2/b.txt', 'b');

        self::assertTrue(Fs::rmrf($dir));
        self::assertDirectoryDoesNotExist($dir);
    }

    public function testRmrfMissingPathReturnsTrue(): void
    {
        self::assertTrue(Fs::rmrf($this->root . '/does-not-exist'));
    }

    public function testRmrfDeepNested(): void
    {
        $dir = $this->root . '/deep';
        mkdir($dir . '/a/b/c/d', 0o775, true);
        file_put_contents($dir . '/a/b/c/d/file.txt', 'x');

        self::assertTrue(Fs::rmrf($dir));
        self::assertDirectoryDoesNotExist($dir);
    }
}
