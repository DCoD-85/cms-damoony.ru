<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DbDescribeCommand;
use Damoony\Cli\Context;
use PHPUnit\Framework\TestCase;

final class DbDescribeCommandTest extends TestCase
{
    private function cmd(): DbDescribeCommand
    {
        return new DbDescribeCommand();
    }

    private function ctx(): Context
    {
        return new Context(static function (): void {
        });
    }

    public function testName(): void
    {
        $this->assertSame('db:describe', $this->cmd()->name());
    }

    public function testUnknownProviderFails(): void
    {
        $r = $this->cmd()->run(['provider' => 'nope', 'method' => 'by_slug'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testMissingMethodFails(): void
    {
        $r = $this->cmd()->run(['provider' => 'news'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testUnknownMethodFails(): void
    {
        $r = $this->cmd()->run(['provider' => 'news', 'method' => 'nope'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testInheritedMethodRejected(): void
    {
        // physicalTable объявлен в AbstractProvider, не переопределён в NewsProvider
        $r = $this->cmd()->run(['provider' => 'news', 'method' => 'physicalTable'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testBySlugDescription(): void
    {
        $r = $this->cmd()->run(['provider' => 'news', 'method' => 'by_slug'], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertSame('news', $r->data['provider']);
        $this->assertSame('by_slug', $r->data['method']);
        $this->assertSame('array', $r->data['return_type']);

        $params = $r->data['params'];
        $this->assertCount(2, $params);
        $this->assertSame('slug', $params[0]['name']);
        $this->assertSame('string', $params[0]['type']);
        $this->assertTrue($params[0]['required']);
        $this->assertSame('lang', $params[1]['name']);
        $this->assertFalse($params[1]['required']);
        $this->assertSame('ru', $params[1]['default']);
    }

    public function testRecentDocblock(): void
    {
        $r = $this->cmd()->run(['provider' => 'news', 'method' => 'recent'], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertStringContainsString('@return list<array<string, mixed>>', $r->data['doc']);
    }
}
