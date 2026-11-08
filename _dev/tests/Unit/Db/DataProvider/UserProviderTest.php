<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Db\DataProvider;

use Damoony\Core\Config;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\UserProvider;
use PHPUnit\Framework\TestCase;

final class UserProviderTest extends TestCase
{
    private Connection $conn;

    private Config $site;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                login TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "active",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );

        $this->site = new Config([]);
    }

    private function provider(?string $domain = 'example.ru', ?string $alias = 'www'): UserProvider
    {
        return new UserProvider($this->conn, $this->site, $domain ?? 'example.ru', $alias ?? 'www');
    }

    public function testNameAndTable(): void
    {
        $p = $this->provider();
        $this->assertSame('user', $p->name());
        $this->assertSame('users', $p->table());
        $this->assertSame('users', $p->physicalTable());
    }

    public function testCreateReturnsId(): void
    {
        $p = $this->provider();
        $id = $p->create('alice', 'hash1', 'alice@example.ru');
        $this->assertSame(1, $id);
    }

    public function testByLoginFindsExisting(): void
    {
        $p = $this->provider();
        $p->create('bob', 'hash_bob', 'bob@example.ru', 'active');

        $row = $p->by_login('bob');
        $this->assertNotEmpty($row);
        $this->assertSame('bob', $row['login']);
        $this->assertSame('bob@example.ru', $row['email']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('hash_bob', $row['password_hash']);
        $this->assertIsInt($row['id']);
    }

    public function testByLoginReturnsEmptyForMissing(): void
    {
        $p = $this->provider();
        $this->assertSame([], $p->by_login('nobody'));
    }

    public function testByLoginReturnsPasswordHash(): void
    {
        $p = $this->provider();
        $p->create('carol', 'crypted_hash', 'carol@example.ru');

        $row = $p->by_login('carol');
        $this->assertNotEmpty($row);
        $this->assertArrayHasKey('password_hash', $row);
        $this->assertSame('crypted_hash', $row['password_hash']);
    }

    public function testById(): void
    {
        $p = $this->provider();
        $id = $p->create('dave', 'hash_dave', 'dave@example.ru');

        $row = $p->by_id($id);
        $this->assertNotEmpty($row);
        $this->assertSame('dave', $row['login']);
    }

    public function testByIdReturnsEmptyForMissing(): void
    {
        $p = $this->provider();
        $this->assertSame([], $p->by_id(9999));
    }

    public function testExists(): void
    {
        $p = $this->provider();
        $p->create('eve', 'hash_eve', 'eve@example.ru');

        $this->assertTrue($p->exists('eve'));
        $this->assertFalse($p->exists('nobody'));
    }

    public function testCreatedAtIsIsoString(): void
    {
        $p = $this->provider();
        $p->create('frank', 'hash_frank', 'frank@example.ru');

        $row = $p->by_login('frank');
        $this->assertNotEmpty($row);
        $this->assertNotEmpty($row['created_at']);
        $this->assertIsString($row['created_at']);
    }

    public function testPhysicalTableUsesPrefixFromSiteConfig(): void
    {
        $site = new Config([
            'domains_aliases_storage' => [
                'd1.ru' => [
                    'www' => ['db' => 'main', 'prefix' => 'd1_www__'],
                ],
            ],
        ]);
        $p = new UserProvider($this->conn, $site, 'd1.ru', 'www');

        $this->assertSame('d1_www__users', $p->physicalTable());
    }

    public function testPhysicalTableFallsBackToNoPrefix(): void
    {
        $p = $this->provider('unknown.ru', 'www');
        $this->assertSame('users', $p->physicalTable());
    }
}
