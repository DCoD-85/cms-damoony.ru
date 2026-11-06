<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\ContentListCommand;
use Damoony\Cli\Commands\ContentStoreCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContentListCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_content_list_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
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

    private function storeImage(string $category, int $seed = 0): string
    {
        $im = imagecreatetruecolor(200, 200);
        if ($im === false) {
            self::fail('imagecreatetruecolor() failed — GD extension is required');
        }
        $color = imagecolorallocate($im, $seed, 100, 50);
        imagefilledrectangle($im, 0, 0, 199, 199, $color);
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();

        new ContentStoreCommand(new Paths($this->root))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => $category,
            'data_b64' => base64_encode($bytes),
            'mime' => 'image/png',
        ], new Context(static fn () => null));

        return substr(hash('sha256', $bytes), 0, 16);
    }

    private function execute(array $args): Result
    {
        return new ContentListCommand(new Paths($this->root))
            ->run($args, new Context(static fn () => null));
    }

    public function testListsAll(): void
    {
        $h1 = $this->storeImage('coffee', 1);
        $h2 = $this->storeImage('coffee', 2);
        $h3 = $this->storeImage('tea', 3);

        $res = $this->execute(['domain' => 'example.ru']);
        self::assertTrue($res->ok);

        $found = implode('|', $res->created);
        self::assertStringContainsString($h1 . '.png', $found);
        self::assertStringContainsString($h2 . '.png', $found);
        self::assertStringContainsString($h3 . '.png', $found);
    }

    public function testFiltersByCategory(): void
    {
        $h1 = $this->storeImage('coffee', 1);
        $h2 = $this->storeImage('tea', 2);

        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www', 'category' => 'coffee']);
        self::assertTrue($res->ok);

        $found = implode('|', $res->created);
        self::assertStringContainsString($h1 . '.png', $found);
        self::assertStringNotContainsString($h2 . '.png', $found);
    }

    public function testEmptyReturnsOk(): void
    {
        $res = $this->execute(['domain' => 'example.ru']);
        self::assertTrue($res->ok);
        self::assertSame([], $res->created);
    }

    public function testRejectsPathTraversal(): void
    {
        $res = $this->execute(['domain' => 'a..ru']);
        self::assertFalse($res->ok);
    }

    public function testRejectsInvalidCategory(): void
    {
        $res = $this->execute(['domain' => 'example.ru', 'category' => 'bad cat!']);
        self::assertFalse($res->ok);
    }

    public function testListsFromContentRootNotMedia(): void
    {
        $h = $this->storeImage('coffee');

        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        self::assertTrue($res->ok);
        self::assertStringContainsString('content/example.ru/www/coffee/' . $h . '.png', implode('|', $res->created));
    }
}
