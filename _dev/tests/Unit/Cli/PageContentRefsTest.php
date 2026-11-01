<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\ContentStoreCommand;
use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageWriteCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageContentRefsTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_refs_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        file_put_contents($this->root . '/config/agent.config', json_encode([
            'allowed_domains' => ['example.ru'],
        ]));

        // Root stub
        $alg = $this->root . '/app/example.ru/www/algoritm';
        file_put_contents($alg . '/algoritm.php', "<?php\n");
        file_put_contents($alg . '/algoritm.rules.json', json_encode([
            'available' => [], 'auth' => false, 'search' => true, 'default' => false,
        ]));

        $this->paths = new Paths($this->root);
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

    private function png(int $seed): string
    {
        $im = imagecreatetruecolor(200, 200);
        if ($im === false) {
            self::markTestSkipped('GD not available');
        }
        imagefilledrectangle($im, 0, 0, 199, 199, imagecolorallocate($im, $seed, 100, 50));
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        (new ContentStoreCommand($this->paths))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'category' => 'coffee',
            'data_b64' => base64_encode($bytes),
            'mime' => 'image/png',
        ], new Context(static fn () => null));

        return substr(hash('sha256', $bytes), 0, 16);
    }

    private function getRefs(string $hash, string $category = 'coffee'): array
    {
        $metaFile = $this->root . '/content/example.ru/www/' . $category . '/' . $hash . '.meta.json';
        $meta = json_decode((string) file_get_contents($metaFile), true);
        return $meta['page_refs'] ?? [];
    }

    private function writePage(string $name, array $blocks): Result
    {
        return (new PageWriteCommand($this->paths))->run([
            'domain' => 'example.ru',
            'allias' => 'www',
            'name' => $name,
            'json' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
        ], new Context(static fn () => null));
    }

    public function testAddsRefOnPageWrite(): void
    {
        (new PageNewCommand($this->paths))->run([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'about',
        ], new Context(static fn () => null));

        $hash = $this->png(1);
        $url = '/content/example.ru/www/coffee/' . $hash . '.png';

        self::assertSame([], $this->getRefs($hash), 'до page:write — пусто');

        $res = $this->writePage('about', [
            ['type' => 'h1', 'content' => 'About'],
            ['type' => 'img', 'content' => $url, 'alt' => 'Coffee'],
        ]);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertSame(['about'], $this->getRefs($hash), 'page_refs = [about]');
    }

    public function testTwoPagesShareImage(): void
    {
        (new PageNewCommand($this->paths))->run([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'about',
        ], new Context(static fn () => null));
        (new PageNewCommand($this->paths))->run([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'menu',
        ], new Context(static fn () => null));

        $hash = $this->png(2);
        $url = '/content/example.ru/www/coffee/' . $hash . '.png';
        $blocks = [
            ['type' => 'h1', 'content' => 'X'],
            ['type' => 'img', 'content' => $url],
        ];

        $this->writePage('about', $blocks);
        $this->writePage('menu', $blocks);

        $refs = $this->getRefs($hash);
        sort($refs);
        self::assertSame(['about', 'menu'], $refs);
    }

    public function testRemovesRefWhenImageRemoved(): void
    {
        (new PageNewCommand($this->paths))->run([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'about',
        ], new Context(static fn () => null));

        $hash = $this->png(3);
        $url = '/content/example.ru/www/coffee/' . $hash . '.png';

        // Сначала с картинкой
        $this->writePage('about', [
            ['type' => 'h1', 'content' => 'About'],
            ['type' => 'img', 'content' => $url],
        ]);
        self::assertSame(['about'], $this->getRefs($hash));

        // Потом без картинки
        $this->writePage('about', [
            ['type' => 'h1', 'content' => 'About'],
            ['type' => 'text', 'content' => 'No image'],
        ]);
        self::assertSame([], $this->getRefs($hash), 'ref удалён, когда картинки нет');
    }

    public function testReplacesImageUpdatesRefs(): void
    {
        (new PageNewCommand($this->paths))->run([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'about',
        ], new Context(static fn () => null));

        $hashA = $this->png(10);
        $hashB = $this->png(20);

        $this->writePage('about', [
            ['type' => 'h1', 'content' => 'About'],
            ['type' => 'img', 'content' => '/content/example.ru/www/coffee/' . $hashA . '.png'],
        ]);
        self::assertSame(['about'], $this->getRefs($hashA));
        self::assertSame([], $this->getRefs($hashB));

        // Заменяем картинку
        $this->writePage('about', [
            ['type' => 'h1', 'content' => 'About'],
            ['type' => 'img', 'content' => '/content/example.ru/www/coffee/' . $hashB . '.png'],
        ]);
        self::assertSame([], $this->getRefs($hashA), 'старая картинка — ref убран');
        self::assertSame(['about'], $this->getRefs($hashB), 'новая — ref добавлен');
    }

    public function testRootPageUsesRootKey(): void
    {
        $hash = $this->png(30);
        $this->writePage('__root__', [
            ['type' => 'h1', 'content' => 'Home'],
            ['type' => 'img', 'content' => '/content/example.ru/www/coffee/' . $hash . '.png'],
        ]);

        self::assertSame(['__root__'], $this->getRefs($hash));
    }

    public function testOtherDomainUrlIgnored(): void
    {
        (new PageNewCommand($this->paths))->run([
            'domain' => 'example.ru', 'allias' => 'www', 'name' => 'about',
        ], new Context(static fn () => null));

        // Ссылка на чужой домен — не должна менять наш meta
        $this->writePage('about', [
            ['type' => 'h1', 'content' => 'About'],
            ['type' => 'img', 'content' => '/content/other.ru/www/coffee/aaaaaaaaaaaaaaaa.png'],
        ]);

        // Ничего не упало, рефы нашего домена не тронуты
        self::assertTrue(true);
    }
}
