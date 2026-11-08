<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageListCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageListCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_page_list_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
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

    private function makeRootPage(): void
    {
        $alg = $this->root . '/app/example.ru/www/algoritm';
        mkdir($alg, 0o777, true);
        file_put_contents($alg . '/algoritm.php', '<?php');
        file_put_contents($alg . '/algoritm.rules.json', json_encode([
            'available' => ['about'], 'auth' => false, 'search' => true, 'default' => false,
        ]));
    }

    private function makePage(string $rel, array $rules = []): void
    {
        $alg = $this->root . '/app/example.ru/www/algoritm';
        $dir = $alg . '/' . $rel;
        mkdir($dir, 0o777, true);
        $class = 'algoritm_' . str_replace('/', '_', $rel);
        file_put_contents($dir . '/' . $class . '.php', '<?php');
        file_put_contents($dir . '/' . $class . '.rules.json', json_encode(array_merge([
            'available' => [], 'auth' => false, 'search' => true, 'default' => false,
        ], $rules)));
    }

    private function execute(array $args): Result
    {
        return new PageListCommand(new Paths($this->root))
            ->run($args, new Context(static fn () => null));
    }

    public function testRejectsInvalidDomain(): void
    {
        $res = $this->execute(['domain' => 'a..ru', 'allias' => 'www']);
        self::assertFalse($res->ok);
    }

    public function testRejectsMissingAlgoritmDir(): void
    {
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        self::assertFalse($res->ok);
    }

    public function testEmptyWhenNoPages(): void
    {
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        self::assertTrue($res->ok);
        self::assertSame(0, $res->data['count']);
    }

    public function testRootPageOnly(): void
    {
        $this->makeRootPage();
        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        self::assertTrue($res->ok);
        self::assertSame(1, $res->data['count']);
        self::assertSame('__root__', $res->data['rows'][0]['name']);
        self::assertSame('/', $res->data['rows'][0]['url']);
        self::assertSame(['about'], $res->data['rows'][0]['available']);
    }

    public function testListsNestedPages(): void
    {
        $this->makeRootPage();
        $this->makePage('about');
        $this->makePage('docs');
        $this->makePage('docs/install', ['auth' => true]);

        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        self::assertTrue($res->ok);
        self::assertSame(4, $res->data['count']);

        $byName = [];
        foreach ($res->data['rows'] as $row) {
            $byName[$row['name']] = $row;
        }
        self::assertArrayHasKey('__root__', $byName);
        self::assertArrayHasKey('about', $byName);
        self::assertArrayHasKey('docs', $byName);
        self::assertArrayHasKey('docs/install', $byName);

        self::assertSame('/docs/install', $byName['docs/install']['url']);
        self::assertTrue($byName['docs/install']['auth']);
    }

    public function testFlagsFromRules(): void
    {
        $this->makeRootPage();
        $this->makePage('private', ['auth' => true, 'search' => false]);
        $this->makePage('home', ['default' => true]);

        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        $byName = [];
        foreach ($res->data['rows'] as $row) {
            $byName[$row['name']] = $row;
        }

        self::assertTrue($byName['private']['auth']);
        self::assertFalse($byName['private']['search']);
        self::assertTrue($byName['home']['default']);
    }

    public function testSkipsHiddenDirs(): void
    {
        $this->makeRootPage();
        $this->makePage('about');
        mkdir($this->root . '/app/example.ru/www/algoritm/.hidden', 0o777, true);
        file_put_contents(
            $this->root . '/app/example.ru/www/algoritm/.hidden/algoritm_.hidden.php',
            '<?php',
        );

        $res = $this->execute(['domain' => 'example.ru', 'allias' => 'www']);
        $names = array_column($res->data['rows'], 'name');
        self::assertContains('about', $names);
        foreach ($names as $n) {
            self::assertStringNotContainsString('.hidden', $n);
        }
    }
}
