<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DbMethodsCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use PHPUnit\Framework\TestCase;

final class DbMethodsCommandTest extends TestCase
{
    private Connection $conn;

    private Config $site;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $this->site = new Config([]);
        $this->paths = new Paths(sys_get_temp_dir());
    }

    private function cmd(): DbMethodsCommand
    {
        return new DbMethodsCommand($this->paths, $this->site, $this->conn);
    }

    private function ctx(): Context
    {
        return new Context(static function (): void {
        });
    }

    public function testName(): void
    {
        $this->assertSame(
            'db:methods',
            $this->cmd()->name(),
        );
    }

    public function testMissingDomainFails(): void
    {
        $r = $this->cmd()->run([], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testUnknownProviderFails(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru', 'provider' => 'unknown'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testNewsProviderMethods(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru', 'provider' => 'news'], $this->ctx());
        $this->assertTrue($r->ok);
        $names = array_column($r->data['methods'], 'name');
        $this->assertContains('recent', $names);
        $this->assertContains('by_slug', $names);
        $this->assertContains('create', $names);
        $this->assertContains('publish', $names);
        $this->assertContains('search_like', $names);
    }

    public function testInheritedMethodsNotListed(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru', 'provider' => 'news'], $this->ctx());
        $names = array_column($r->data['methods'], 'name');
        $this->assertNotContains('table', $names);
        $this->assertNotContains('physicalTable', $names);
        $this->assertNotContains('name', $names);
    }

    public function testBySlugSignature(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru', 'provider' => 'news'], $this->ctx());
        $bySlug = array_values(array_filter(
            $r->data['methods'],
            static fn (array $m): bool => $m['name'] === 'by_slug',
        ))[0];

        $this->assertSame('array', $bySlug['return_type']);
        $params = $bySlug['params'];
        $this->assertCount(2, $params);

        $this->assertSame('slug', $params[0]['name']);
        $this->assertSame('string', $params[0]['type']);
        $this->assertTrue($params[0]['required']);

        $this->assertSame('lang', $params[1]['name']);
        $this->assertSame('string', $params[1]['type']);
        $this->assertFalse($params[1]['required']);
        $this->assertSame('ru', $params[1]['default']);
    }

    public function testUserProviderMethods(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru', 'provider' => 'user'], $this->ctx());
        $this->assertTrue($r->ok);
        $names = array_column($r->data['methods'], 'name');
        $this->assertSame(['by_id', 'by_login', 'create', 'exists'], $names);
    }
}
