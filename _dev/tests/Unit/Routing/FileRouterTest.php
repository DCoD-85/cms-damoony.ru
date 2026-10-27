<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Routing;

use Damoony\Domain\DomainContext;
use Damoony\Routing\FileRouter;
use Damoony\Routing\RulesMatcher;
use PHPUnit\Framework\TestCase;

final class FileRouterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_router_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o777, true);
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
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function writeAlgoritm(string $real, string $allias, string $rel, array $rules, bool $withPhp = true): void
    {
        $base = $this->root . '/app/' . $real . '/' . $allias . '/algoritm';
        $dir = $rel === '' ? $base : $base . '/' . $rel;
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($dir . '/.rules.json', json_encode($rules));
        if ($withPhp) {
            $class = 'algoritm' . ($rel === '' ? '' : '_' . str_replace('/', '_', $rel));
            file_put_contents($dir . '/' . $class . '.php', "<?php\n");
        }
    }

    private function ctx(): DomainContext
    {
        return new DomainContext(
            domain: 'example.ru',
            real: 'example.ru',
            allias: 'www',
            logic: 'alpha.0.0',
            skin: 'default',
            language: 'ru-ru',
            available: true,
        );
    }

    private function router(): FileRouter
    {
        return new FileRouter($this->root, new RulesMatcher());
    }

    public function testRootMatch(): void
    {
        $this->writeAlgoritm('example.ru', 'www', '', ['available' => ['']]);
        $m = $this->router()->match('/', $this->ctx());
        self::assertTrue($m->found);
        self::assertSame('algoritm', $m->classChain);
        self::assertSame('', $m->pathRel);
    }

    public function testExactSegment(): void
    {
        $this->writeAlgoritm('example.ru', 'www', '', ['available' => ['about']]);
        $this->writeAlgoritm('example.ru', 'www', 'about', ['available' => ['']]);
        $m = $this->router()->match('/about', $this->ctx());
        self::assertTrue($m->found);
        self::assertSame('algoritm_about', $m->classChain);
        self::assertSame('about', $m->pathRel);
    }

    public function testRegexSegmentWithArg(): void
    {
        $this->writeAlgoritm('example.ru', 'www', '', ['available' => ['([a-z-]+)|slug']]);
        $this->writeAlgoritm('example.ru', 'www', 'slug', ['available' => ['']]);
        $m = $this->router()->match('/hello-world', $this->ctx());
        self::assertTrue($m->found);
        self::assertSame('algoritm_slug', $m->classChain);
        self::assertSame(['slug' => 'hello-world'], $m->args);
    }

    public function testMissingBaseReturnsNotFound(): void
    {
        $m = $this->router()->match('/', $this->ctx());
        self::assertFalse($m->found);
        self::assertSame(404, $m->code);
    }

    public function testMissingRulesReturnsNotFound(): void
    {
        $base = $this->root . '/app/example.ru/www/algoritm';
        mkdir($base, 0o777, true);

        $m = $this->router()->match('/', $this->ctx());
        self::assertFalse($m->found);
    }

    public function testUnknownSegmentReturnsNotFound(): void
    {
        $this->writeAlgoritm('example.ru', 'www', '', ['available' => ['about']]);
        $m = $this->router()->match('/nope', $this->ctx());
        self::assertFalse($m->found);
    }

    public function testDeepMatch(): void
    {
        $this->writeAlgoritm('example.ru', 'www', '', ['available' => ['docs']]);
        $this->writeAlgoritm('example.ru', 'www', 'docs', ['available' => ['([a-z]+)|page']]);
        $this->writeAlgoritm('example.ru', 'www', 'docs/page', ['available' => ['']]);
        $m = $this->router()->match('/docs/intro', $this->ctx());
        self::assertTrue($m->found);
        self::assertSame('algoritm_docs_page', $m->classChain);
        self::assertSame(['page' => 'intro'], $m->args);
    }
}
