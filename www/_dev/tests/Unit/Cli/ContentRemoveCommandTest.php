<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\ContentRemoveCommand;
use Damoony\Cli\Commands\ContentStoreCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContentRemoveCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_content_rm_' . bin2hex(random_bytes(4));

        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode(['domains_real' => ['example.ru' => 'example.ru', 'damoony.ru' => 'damoony.ru', 'damoony3.ru' => 'damoony3.ru']]));
        file_put_contents($this->root . '/config/agent.config', json_encode([
            'allowed_domains' => ['example.ru'],
        ]));
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
            self::fail('imagecreatetruecolor() failed — GD extension is required');
        }
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();

        new ContentStoreCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'data_b64' => base64_encode($bytes),
            'mime' => 'image/png',
        ], new Context(static fn () => null));

        return ['hash' => substr(hash('sha256', $bytes), 0, 16), 'bytes' => $bytes];
    }

    private function execute(array $args): Result
    {
        return new ContentRemoveCommand(new Paths($this->root))
            ->run($args, new Context(static fn () => null));
    }

    public function testRemovesToTrash(): void
    {
        $img = $this->storeImage();
        $file = $this->root . '/content/example.ru/www/coffee/' . $img['hash'] . '.png';
        self::assertFileExists($file);

        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => $img['hash'],
            'force' => '1',
        ]);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertFileDoesNotExist($file);

        // Карантин в /content/.trash/
        $trash = glob($this->root . '/content/.trash/example.ru_www_coffee_' . $img['hash'] . '_trash_*');
        self::assertNotEmpty($trash);
        self::assertFileExists($trash[0] . '/' . $img['hash'] . '.png');
        self::assertFileExists($trash[0] . '/' . $img['hash'] . '.meta.json');
    }

    public function testRejectsWithoutForce(): void
    {
        $img = $this->storeImage();
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => $img['hash'],
        ]);

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testIdempotentOnMissing(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => '0000000000000000',
            'force' => '1',
        ]);

        self::assertTrue($res->ok);
        self::assertNotEmpty($res->warnings);
    }

    public function testRejectsInvalidHash(): void
    {
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => 'not-hex-!!',
            'force' => '1',
        ]);

        self::assertFalse($res->ok);
        self::assertStringContainsString('hash', $res->errors[0]);
    }

    public function testRejectsPathTraversal(): void
    {
        $res = $this->execute([
            'domain' => 'a..ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => '0000000000000000',
            'force' => '1',
        ]);

        self::assertFalse($res->ok);
    }

    public function testTrashInContentRootNotMedia(): void
    {
        $img = $this->storeImage();
        $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'hash' => $img['hash'],
            'force' => '1',
        ]);

        // Карантин — в /content/.trash/, не в www/media
        self::assertDirectoryExists($this->root . '/content/.trash');
        self::assertDirectoryDoesNotExist($this->root . '/www/media/.trash');
    }
}
