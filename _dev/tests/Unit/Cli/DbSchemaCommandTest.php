<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DbSchemaCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use PHPUnit\Framework\TestCase;

final class DbSchemaCommandTest extends TestCase
{
    private Connection $conn;

    private Config $site;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE news (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL, published_at TEXT)');

        $this->site = new Config([]);
    }

    private function cmd(?Config $site = null): DbSchemaCommand
    {
        return new DbSchemaCommand($site ?? $this->site, $this->conn);
    }

    private function ctx(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    public function testName(): void
    {
        $this->assertSame('db:schema', $this->cmd()->name());
    }

    public function testMissingDomainFails(): void
    {
        $r = $this->cmd()->run([], $this->ctx());
        $this->assertFalse($r->ok);
        $this->assertNotEmpty($r->errors);
    }

    public function testInvalidDomainFails(): void
    {
        $r = $this->cmd()->run(['domain' => 'not-a-domain'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testInvalidAliasFails(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru', 'alias' => 'Bad-Alias!'], $this->ctx());
        $this->assertFalse($r->ok);
    }

    public function testListsAllTablesWithoutPrefix(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru'], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertSame('sqlite', $r->data['driver']);
        $this->assertSame('', $r->data['prefix']);

        $names = array_column($r->data['tables'], 'name');
        $this->assertContains('users', $names);
        $this->assertContains('news', $names);
    }

    public function testColumnsOfUsers(): void
    {
        $r = $this->cmd()->run(['domain' => 'example.ru'], $this->ctx());
        $users = array_values(array_filter(
            $r->data['tables'],
            static fn (array $t): bool => $t['name'] === 'users',
        ))[0];
        $this->assertSame('users', $users['table']);

        $colNames = array_column($users['columns'], 'name');
        $this->assertContains('id', $colNames);
        $this->assertContains('login', $colNames);

        $idCol = array_values(array_filter(
            $users['columns'],
            static fn (array $c): bool => $c['name'] === 'id',
        ))[0];
        $this->assertSame('PRI', $idCol['key']);
    }

    public function testPrefixFiltersTables(): void
    {
        $pdo = $this->conn->pdo();
        $pdo->exec('CREATE TABLE d1_www__articles (id INTEGER PRIMARY KEY, title TEXT)');

        $site = new Config([
            'domains_aliases_storage' => [
                'example.ru' => ['www' => ['db' => 'main', 'prefix' => 'd1_www__']],
            ],
        ]);

        $r = $this->cmd($site)->run(['domain' => 'example.ru', 'alias' => 'www'], $this->ctx());
        $this->assertTrue($r->ok);
        $this->assertSame('d1_www__', $r->data['prefix']);

        $names = array_column($r->data['tables'], 'name');
        $this->assertContains('d1_www__articles', $names);
        $this->assertContains('users', $names);       // global — passes prefix filter
        $this->assertNotContains('news', $names);     // unprefixed non-global — filtered out

        $articles = array_values(array_filter(
            $r->data['tables'],
            static fn (array $t): bool => $t['name'] === 'd1_www__articles',
        ))[0];
        $this->assertSame('articles', $articles['table']);  // logical name stripped
    }
}
