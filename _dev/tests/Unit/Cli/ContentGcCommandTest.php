<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\ContentGcCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class ContentGcCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_gc_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru', 'other.ru' => 'other.ru'],
            'domains_available' => ['example.ru' => true, 'other.ru' => true],
            'domains_namespace' => ['example.ru' => 'example', 'other.ru' => 'other'],
            'alliases_real' => [
                'example.ru' => ['www' => 'www'],
                'other.ru' => ['www' => 'www'],
            ],
        ]));
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);
        mkdir($this->root . '/app/other.ru/www/algoritm', 0o777, true);
        mkdir($this->root . '/content', 0o777, true);
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

    private function ctx(Result $r): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = '') use ($r): void {
            $r->step($m, $ok, $d);
        });
    }

    /** @param array<string, mixed>|null $meta null — не создавать .meta.json */
    private function makeContent(
        string $domain,
        string $allias,
        string $category,
        string $hash,
        ?array $meta,
        string $bytes = 'PNG',
    ): void {
        $dir = $this->root . '/content/' . $domain . '/' . $allias . '/' . $category;
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/' . $hash . '.png', $bytes);
        if ($meta !== null) {
            file_put_contents($dir . '/' . $hash . '.meta.json', (string) json_encode($meta));
        }
    }

    private function makePage(string $domain, string $allias, string $pageKey): void
    {
        $page = \Damoony\Page\PagePath::resolve(new Paths($this->root), $domain, $allias, $pageKey, '');
        if (!is_dir($page->pageDir)) {
            mkdir($page->pageDir, 0o777, true);
        }
        touch($page->phpFile);
    }

    public function testRequiresForceOrDry(): void
    {
        $res = new ContentGcCommand(new Paths($this->root))
            ->run([], $this->ctx(new Result()));
        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testDryDoesNotTouch(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', 'aaaaaaaaaaaaaaaa', ['page_refs' => []]);
        $path = $this->root . '/content/example.ru/www/coffee/aaaaaaaaaaaaaaaa.png';
        self::assertFileExists($path);

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['dry' => '1', 'force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileExists($path, '--dry не должен трогать файлы');
    }

    public function testEmptyRefsOrphan(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', 'aaaaaaaaaaaaaaaa', ['page_refs' => []]);

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileDoesNotExist($this->root . '/content/example.ru/www/coffee/aaaaaaaaaaaaaaaa.png');
        self::assertDirectoryExists($this->root . '/content/.trash');
    }

    public function testMissingPageOrphan(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', 'bbbbbbbbbbbbbbbb', ['page_refs' => ['ghost']]);

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileDoesNotExist($this->root . '/content/example.ru/www/coffee/bbbbbbbbbbbbbbbb.png');
    }

    public function testAliveKept(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', 'cccccccccccccccc', ['page_refs' => ['about']]);
        $this->makePage('example.ru', 'www', 'about');

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileExists($this->root . '/content/example.ru/www/coffee/cccccccccccccccc.png');
    }

    public function testRootRefAlive(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', 'dddddddddddddddd', ['page_refs' => ['__root__']]);
        $this->makePage('example.ru', 'www', '');

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileExists($this->root . '/content/example.ru/www/coffee/dddddddddddddddd.png');
    }

    public function testDomainFilter(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', 'eeeeeeeeeeeeeeee', ['page_refs' => []]);
        $this->makeContent('other.ru', 'www', 'coffee', 'ffffffffffffffff', ['page_refs' => []]);

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1', 'domain' => 'example.ru'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileDoesNotExist($this->root . '/content/example.ru/www/coffee/eeeeeeeeeeeeeeee.png');
        self::assertFileExists(
            $this->root . '/content/other.ru/www/coffee/ffffffffffffffff.png',
            'other.ru не должен быть затронут при --domain=example.ru',
        );
    }

    public function testMissingMetaWarned(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', '1111111111111111', null);
        $path = $this->root . '/content/example.ru/www/coffee/1111111111111111.png';
        self::assertFileExists($path);

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertStringContainsString('нет meta.json', implode(' | ', $res->warnings));
        self::assertFileExists($path, 'Файл без meta.json — не трогаем');
    }

    public function testOlderFilter(): void
    {
        $this->makeContent('example.ru', 'www', 'coffee', '2222222222222222', ['page_refs' => []]);
        $metaPath = $this->root . '/content/example.ru/www/coffee/2222222222222222.meta.json';
        touch($metaPath, time() - 3600); // 1 час назад — свежее 7 дней

        $res = new ContentGcCommand(new Paths($this->root))
            ->run(['force' => '1', 'older' => '7d'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertFileExists(
            $this->root . '/content/example.ru/www/coffee/2222222222222222.png',
            '--older=7d не трогает свежий контент',
        );
    }
}
