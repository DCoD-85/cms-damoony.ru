<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Db\DataProvider;

use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Db\DataProvider\AuditProvider;
use PHPUnit\Framework\TestCase;

final class AuditProviderTest extends TestCase
{
    private Connection $conn;

    private Config $site;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec(
            'CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                domain TEXT NOT NULL,
                alias_name TEXT NOT NULL,
                provider TEXT NOT NULL,
                method TEXT NOT NULL,
                params_json TEXT,
                result_ok INTEGER NOT NULL,
                error_text TEXT,
                source TEXT NOT NULL,
                role_name TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
        $this->site = new Config([]);
    }

    private function provider(): AuditProvider
    {
        return new AuditProvider($this->conn, $this->site, 'example.ru', 'www');
    }

    public function testPhysicalTableIsGlobal(): void
    {
        // даже при настроенном prefix audit_log остаётся без префикса
        $site = new Config([
            'domains_aliases_storage' => [
                'd1.ru' => ['www' => ['db' => 'main', 'prefix' => 'd1_www__']],
            ],
        ]);
        $p = new AuditProvider($this->conn, $site, 'd1.ru', 'www');
        $this->assertSame('audit_log', $p->physicalTable());
    }

    public function testLogReturnsId(): void
    {
        $id = $this->provider()->log('example.ru', 'www', 'news', 'create', ['slug' => 'a'], true, '');
        $this->assertGreaterThan(0, $id);
    }

    public function testRecent(): void
    {
        $p = $this->provider();
        $p->log('example.ru', 'www', 'news', 'create', ['slug' => 'a'], true, '');
        $p->log('example.ru', 'www', 'news', 'update', ['id' => 1], false, 'syntax error');

        $rows = $p->recent(10);
        $this->assertCount(2, $rows);
        // DESC order — последний первым
        $this->assertSame('update', $rows[0]['method']);
        $this->assertFalse($rows[0]['ok']);
        $this->assertSame('syntax error', $rows[0]['error']);
        $this->assertSame('create', $rows[1]['method']);
        $this->assertTrue($rows[1]['ok']);
    }

    public function testByEntity(): void
    {
        $p = $this->provider();
        $p->log('example.ru', 'www', 'news', 'create', null, true, '');
        $p->log('example.ru', 'www', 'news', 'update', null, true, '');
        $p->log('example.ru', 'www', 'files', 'create', null, true, '');

        $rows = $p->by_entity('news', 'create', 10);
        $this->assertCount(1, $rows);
        $this->assertSame('news', $rows[0]['provider']);
        $this->assertSame('create', $rows[0]['method']);
    }

    public function testParamsJsonStored(): void
    {
        $p = $this->provider();
        $p->log('example.ru', 'www', 'news', 'create', ['slug' => 'a', 'title' => 'A'], true, '');

        $rows = $p->recent(1);
        $this->assertNotEmpty($rows[0]['params']);
        $decoded = json_decode((string) $rows[0]['params'], true);
        $this->assertSame('a', $decoded['slug']);
    }

    public function testSourceAndRole(): void
    {
        $p = $this->provider();
        $p->log('example.ru', 'www', 'news', 'create', null, true, '', 'ai', 'executor');

        $rows = $p->recent(1);
        $this->assertSame('ai', $rows[0]['source']);
        $this->assertSame('executor', $rows[0]['role']);
    }

    public function testRecentLimitClamped(): void
    {
        $p = $this->provider();
        for ($i = 0; $i < 5; $i++) {
            $p->log('example.ru', 'www', 'news', 'create', null, true, '');
        }
        $this->assertCount(2, $p->recent(2));
        $this->assertCount(1, $p->recent(0));
        $this->assertCount(5, $p->recent(999));
    }
}
