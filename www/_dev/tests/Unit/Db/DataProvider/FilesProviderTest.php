<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Db\DataProvider;

use Damoony\Core\Config;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\FilesProvider;
use PHPUnit\Framework\TestCase;

final class FilesProviderTest extends TestCase
{
    private Connection $conn;

    private Config $site;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec(
            'CREATE TABLE files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                filename TEXT NOT NULL,
                filesize INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT "active",
                downloads INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );

        $this->site = new Config([]);
    }

    private function provider(?string $domain = 'example.ru', ?string $alias = 'www'): FilesProvider
    {
        return new FilesProvider($this->conn, $this->site, $domain ?? 'example.ru', $alias ?? 'www');
    }

    public function testNameAndTable(): void
    {
        $p = $this->provider();
        $this->assertSame('files', $p->name());
        $this->assertSame('files', $p->table());
        $this->assertSame('files', $p->physicalTable());
    }

    public function testPhysicalTableUsesPrefix(): void
    {
        $site = new Config([
            'domains_aliases_storage' => [
                'd1.ru' => ['www' => ['db' => 'main', 'prefix' => 'd1_www__']],
            ],
        ]);
        $p = new FilesProvider($this->conn, $site, 'd1.ru', 'www');
        $this->assertSame('d1_www__files', $p->physicalTable());
    }

    public function testCreateAndById(): void
    {
        $p = $this->provider();
        $id = $p->create('doc1', 'doc1.pdf', 1024);
        $this->assertGreaterThan(0, $id);

        $row = $p->by_id($id);
        $this->assertNotEmpty($row);
        $this->assertSame('doc1', $row['slug']);
        $this->assertSame('doc1.pdf', $row['filename']);
        $this->assertSame(1024, $row['filesize']);
        $this->assertSame('active', $row['status']);
        $this->assertSame(0, $row['downloads']);
    }

    public function testCreateWithoutRequiredReturnsZero(): void
    {
        $p = $this->provider();
        $this->assertSame(0, $p->create('', 'file.pdf'));
        $this->assertSame(0, $p->create('slug', ''));
    }

    public function testBySlug(): void
    {
        $p = $this->provider();
        $p->create('a', 'a.pdf', 100);

        $row = $p->by_slug('a');
        $this->assertNotEmpty($row);
        $this->assertSame('a', $row['slug']);

        $this->assertSame([], $p->by_slug('missing'));
    }

    public function testAllActive(): void
    {
        $p = $this->provider();
        $p->create('a', 'a.pdf');
        $p->create('b', 'b.pdf');
        $p->create('hidden', 'h.pdf', 0, 'hidden');

        $rows = $p->all_active();
        $slugs = array_column($rows, 'slug');
        $this->assertContains('a', $slugs);
        $this->assertContains('b', $slugs);
        $this->assertNotContains('hidden', $slugs);
    }

    public function testUpdateFilesize(): void
    {
        $p = $this->provider();
        $id = $p->create('x', 'x.pdf', 100);

        $p->update_filesize($id, 5000);

        $row = $p->by_id($id);
        $this->assertSame(5000, $row['filesize']);
    }

    public function testUpdateFilesizeNegativeClampedToZero(): void
    {
        $p = $this->provider();
        $id = $p->create('y', 'y.pdf', 100);

        $p->update_filesize($id, -999);

        $row = $p->by_id($id);
        $this->assertSame(0, $row['filesize']);
    }

    public function testIncrementDownloads(): void
    {
        $p = $this->provider();
        $id = $p->create('z', 'z.pdf');
        $this->assertSame(0, $p->by_id($id)['downloads']);

        $p->increment_downloads($id);
        $p->increment_downloads($id);
        $p->increment_downloads($id);

        $this->assertSame(3, $p->by_id($id)['downloads']);
    }

    public function testIncrementDownloadsOnMissingIdIsNoop(): void
    {
        $p = $this->provider();
        // no exception — UPDATE без строк = 0 affected
        $p->increment_downloads(9999);
        $this->assertTrue(true);
    }

    public function testDelete(): void
    {
        $p = $this->provider();
        $id = $p->create('del', 'del.pdf');
        $this->assertNotEmpty($p->by_id($id));

        $p->delete($id);
        $this->assertSame([], $p->by_id($id));
    }
}
