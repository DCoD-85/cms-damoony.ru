<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\MediaRemoveCommand;
use Damoony\Cli\Commands\MediaStoreCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class MediaRemoveCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_media_rm_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/agent.config', json_encode([
            'allowed_domains' => ['example.ru'],
        ]));
        mkdir($this->root . '/www', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /** @return array{hash: string, bytes: string} */
    private function storeImage(): array
    {
        $im = imagecreatetruecolor(200, 200);
        if ($im === false) {
            self::markTestSkipped('GD not available');
        }
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        (new MediaStoreCommand(new Paths($this->root)))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'data_b64' => base64_encode($bytes),
            'mime' => 'image/png',
        ], new Context(static fn () => null));

        return ['hash' => substr(hash('sha256', $bytes), 0, 16), 'bytes' => $bytes];
    }

    public function testRemovesToTrash(): void
    {
        $img = $this->storeImage();
        $file = $this->root . '/www/media/example.ru/www/coffee/' . $img['hash'] . '.png';
        self::assertFileExists($file);

        $res = (new MediaRemoveCommand(new Paths($this->root)))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => $img['hash'],
            'force' => '1',
        ], new Context(static fn () => null));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertFileDoesNotExist($file);

        $trash = glob($this->root . '/www/media/.trash/example.ru_www_coffee_' . $img['hash'] . '_trash_*');
        self::assertNotEmpty($trash);
        self::assertFileExists($trash[0] . '/' . $img['hash'] . '.png');
        self::assertFileExists($trash[0] . '/' . $img['hash'] . '.meta.json');
    }

    public function testRejectsWithoutForce(): void
    {
        $img = $this->storeImage();
        $res = (new MediaRemoveCommand(new Paths($this->root)))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => $img['hash'],
        ], new Context(static fn () => null));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testIdempotentOnMissing(): void
    {
        $res = (new MediaRemoveCommand(new Paths($this->root)))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => '0000000000000000',
            'force' => '1',
        ], new Context(static fn () => null));

        self::assertTrue($res->ok);
        self::assertNotEmpty($res->warnings);
    }

    public function testRejectsInvalidHash(): void
    {
        $res = (new MediaRemoveCommand(new Paths($this->root)))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => 'not-hex-!!',
            'force' => '1',
        ], new Context(static fn () => null));

        self::assertFalse($res->ok);
        self::assertStringContainsString('hash', $res->errors[0]);
    }

    public function testRejectsPathTraversal(): void
    {
        $res = (new MediaRemoveCommand(new Paths($this->root)))->run([
            'domain' => 'a..ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => '0000000000000000',
            'force' => '1',
        ], new Context(static fn () => null));

        self::assertFalse($res->ok);
    }
}
