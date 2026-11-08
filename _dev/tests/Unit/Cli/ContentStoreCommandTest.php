<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\ContentStoreCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContentStoreCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_content_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/agent.config', json_encode([
            'allowed_domains' => ['example.ru', 'damoony3test.ru'],
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

    /** @return array{bytes: string, base64: string} */
    private function png(int $width = 200, int $height = 200): array
    {
        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            self::fail('imagecreatetruecolor() failed — GD extension is required');
        }
        $bg = imagecolorallocate($im, 200, 100, 50);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $bg);
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();

        return ['bytes' => $bytes, 'base64' => base64_encode($bytes)];
    }

    private function execute(array $args): Result
    {
        return new ContentStoreCommand(new Paths($this->root))
            ->run($args, new Context(static fn () => null));
    }

    public function testStoresImage(): void
    {
        $png = $this->png(300, 400);
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
            'alt' => 'Coffee cup',
        ]);

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $hash = substr(hash('sha256', $png['bytes']), 0, 16);
        $file = $this->root . '/content/example.ru/www/coffee/' . $hash . '.png';
        self::assertFileExists($file);
        self::assertSame($png['bytes'], (string) file_get_contents($file));

        // Проверка: файл НЕ в www/media
        self::assertFileDoesNotExist($this->root . '/www/media/example.ru/www/coffee/' . $hash . '.png');

        $metaFile = $this->root . '/content/example.ru/www/coffee/' . $hash . '.meta.json';
        self::assertFileExists($metaFile);
        $meta = json_decode((string) file_get_contents($metaFile), true);
        self::assertSame('Coffee cup', $meta['alt']);
        self::assertSame('image/png', $meta['mime']);
        self::assertSame(300, $meta['width']);
        self::assertSame(400, $meta['height']);
        self::assertSame([], $meta['page_refs'], 'page_refs инициализируется пустым');
    }

    public function testDeduplicates(): void
    {
        $png = $this->png();
        $args = [
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
        ];
        $r1 = $this->execute($args);
        $r2 = $this->execute($args);

        self::assertTrue($r1->ok);
        self::assertTrue($r2->ok);
        self::assertContains('deduplicated', $r2->warnings);
    }

    public function testRejectsInvalidDomain(): void
    {
        $png = $this->png();
        $res = $this->execute([
            'domain' => 'a..ru',
            'allias' => 'www',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
        ]);
        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректный домен', $res->errors[0]);
    }

    public function testRejectsDomainNotInAgentConfig(): void
    {
        $png = $this->png();
        $res = $this->execute([
            'domain' => 'evil.com',
            'allias' => 'www',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
        ]);
        self::assertFalse($res->ok);
        self::assertStringContainsString('allowed_domains', $res->errors[0]);
    }

    public function testRejectsInvalidMime(): void
    {
        $png = $this->png();
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'data_b64' => $png['base64'],
            'mime' => 'application/pdf',
        ]);
        self::assertFalse($res->ok);
        self::assertStringContainsString('MIME', $res->errors[0]);
    }

    public function testRejectsTooSmallImage(): void
    {
        $png = $this->png(50, 50);
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
        ]);
        self::assertFalse($res->ok);
        self::assertStringContainsString('слишком маленькое', $res->errors[0]);
    }

    public function testUsesContentRootNotMedia(): void
    {
        $png = $this->png();
        $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
        ]);

        $hash = substr(hash('sha256', $png['bytes']), 0, 16);

        // В /content/ — есть
        self::assertFileExists($this->root . '/content/example.ru/www/coffee/' . $hash . '.png');

        // В www/media/ — нет
        self::assertDirectoryDoesNotExist($this->root . '/www/media/example.ru');
    }

    public function testDefaultCategoryGeneral(): void
    {
        $png = $this->png();
        $res = $this->execute([
            'domain' => 'example.ru',
            'allias' => 'www',
            'data_b64' => $png['base64'],
            'mime' => 'image/png',
        ]);
        self::assertTrue($res->ok);
        $hash = substr(hash('sha256', $png['bytes']), 0, 16);
        self::assertFileExists($this->root . '/content/example.ru/www/general/' . $hash . '.png');
    }
}
