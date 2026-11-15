<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Db\DataProvider;

use Damoony\Core\Config;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\NewsProvider;
use PHPUnit\Framework\TestCase;

final class NewsProviderTest extends TestCase
{
    private Connection $conn;

    private Config $site;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec(
            'CREATE TABLE news (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                lang TEXT NOT NULL DEFAULT "ru",
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                published_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );

        $this->site = new Config([]);
    }

    private function provider(?string $domain = 'example.ru', ?string $alias = 'www'): NewsProvider
    {
        return new NewsProvider($this->conn, $this->site, $domain ?? 'example.ru', $alias ?? 'www');
    }

    public function testNameAndTable(): void
    {
        $p = $this->provider();
        $this->assertSame('news', $p->name());
        $this->assertSame('news', $p->table());
        $this->assertSame('news', $p->physicalTable());
    }

    public function testPhysicalTableUsesPrefix(): void
    {
        $site = new Config([
            'domains_aliases_storage' => [
                'd1.ru' => ['www' => ['db' => 'main', 'prefix' => 'd1_www__']],
            ],
        ]);
        $p = new NewsProvider($this->conn, $site, 'd1.ru', 'www');
        $this->assertSame('d1_www__news', $p->physicalTable());
    }

    public function testCreateAndById(): void
    {
        $p = $this->provider();
        $id = $p->create([
            'slug' => 'hello',
            'title' => 'Hello',
            'content' => 'Body',
            'lang' => 'ru',
            'status' => 'draft',
        ]);
        $this->assertGreaterThan(0, $id);

        $row = $p->by_id($id);
        $this->assertNotEmpty($row);
        $this->assertSame('hello', $row['slug']);
        $this->assertSame('Hello', $row['title']);
        $this->assertSame('draft', $row['status']);
    }

    public function testCreateWithoutRequiredReturnsZero(): void
    {
        $p = $this->provider();
        $this->assertSame(0, $p->create(['title' => 'No slug']));
        $this->assertSame(0, $p->create(['slug' => 'no-title']));
    }

    public function testCreateIgnoresNonWritableFields(): void
    {
        $p = $this->provider();
        $id = $p->create([
            'slug' => 'x',
            'title' => 'X',
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);
        $row = $p->by_id($id);
        $this->assertNotSame(999, $row['id']);
        $this->assertStringStartsWith('20', $row['created_at']);
    }

    public function testBySlug(): void
    {
        $p = $this->provider();
        $p->create(['slug' => 'about', 'title' => 'About', 'lang' => 'ru']);

        $row = $p->by_slug('about', 'ru');
        $this->assertNotEmpty($row);
        $this->assertSame('about', $row['slug']);

        $this->assertSame([], $p->by_slug('about', 'en'));
        $this->assertSame([], $p->by_slug('missing', 'ru'));
    }

    public function testUpdateWhitelist(): void
    {
        $p = $this->provider();
        $id = $p->create(['slug' => 'a', 'title' => 'A', 'content' => 'old']);

        $p->update($id, [
            'title' => 'B',
            'content' => 'new',
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $row = $p->by_id($id);
        $this->assertSame('B', $row['title']);
        $this->assertSame('new', $row['content']);
        $this->assertSame($id, $row['id']);
        $this->assertNotSame('2000-01-01 00:00:00', $row['created_at']);
    }

    public function testUpdateEmptyNoop(): void
    {
        $p = $this->provider();
        $id = $p->create(['slug' => 'b', 'title' => 'B']);

        $p->update($id, ['id' => 1, 'created_at' => 'x']);
        $row = $p->by_id($id);
        $this->assertSame('B', $row['title']);
    }

    public function testPublish(): void
    {
        $p = $this->provider();
        $id = $p->create(['slug' => 'c', 'title' => 'C']);
        $this->assertNull($p->by_id($id)['published_at']);

        $p->publish($id);
        $row = $p->by_id($id);
        $this->assertSame('published', $row['status']);
        $this->assertNotNull($row['published_at']);
    }

    public function testPublishKeepsExistingPublishedAt(): void
    {
        $p = $this->provider();
        $id = $p->create([
            'slug' => 'd',
            'title' => 'D',
            'published_at' => '2026-01-01 12:00:00',
        ]);
        $p->publish($id);
        $row = $p->by_id($id);
        $this->assertSame('2026-01-01 12:00:00', $row['published_at']);
    }

    public function testDelete(): void
    {
        $p = $this->provider();
        $id = $p->create(['slug' => 'e', 'title' => 'E']);
        $this->assertNotEmpty($p->by_id($id));

        $p->delete($id);
        $this->assertSame([], $p->by_id($id));
    }

    public function testRecentPublishedOnlyAndLang(): void
    {
        $p = $this->provider();
        $id1 = $p->create(['slug' => 'a1', 'title' => 'A1', 'lang' => 'ru']);
        $p->publish($id1);
        $id2 = $p->create(['slug' => 'a2', 'title' => 'A2', 'lang' => 'ru']);
        $p->publish($id2);
        $p->create(['slug' => 'a3', 'title' => 'A3', 'lang' => 'ru']); // draft
        $id4 = $p->create(['slug' => 'a4', 'title' => 'A4', 'lang' => 'en']);
        $p->publish($id4);

        $rows = $p->recent(10, 'ru');
        $slugs = array_column($rows, 'slug');
        $this->assertContains('a1', $slugs);
        $this->assertContains('a2', $slugs);
        $this->assertNotContains('a3', $slugs);
        $this->assertNotContains('a4', $slugs);
    }

    public function testSearchLike(): void
    {
        $p = $this->provider();
        $id1 = $p->create(['slug' => 's1', 'title' => 'About dogs', 'content' => 'Cats too']);
        $p->publish($id1);
        $id2 = $p->create(['slug' => 's2', 'title' => 'About cats', 'content' => 'Nothing']);
        $p->publish($id2);
        $id3 = $p->create(['slug' => 's3', 'title' => 'Nothing', 'content' => 'Secret dogs']); // draft

        $byTitle = $p->search_like('dog');
        $slugs = array_column($byTitle, 'slug');
        $this->assertContains('s1', $slugs);
        $this->assertNotContains('s2', $slugs);
        $this->assertNotContains('s3', $slugs); // draft

        $byContent = $p->search_like('cats');
        $slugs = array_column($byContent, 'slug');
        $this->assertContains('s1', $slugs); // content contains "Cats"
        $this->assertContains('s2', $slugs); // title contains "cats"

        $none = $p->search_like('missing-term');
        $this->assertSame([], $none);
    }

    public function testRecentLimitClamped(): void
    {
        $p = $this->provider();
        for ($i = 1; $i <= 3; $i++) {
            $id = $p->create(['slug' => "n$i", 'title' => "N$i"]);
            $p->publish($id);
        }
        $this->assertCount(2, $p->recent(2));
        $this->assertCount(1, $p->recent(0)); // clamped to 1 (max(1, ...))
        $this->assertCount(3, $p->recent(200)); // clamped to 100, but only 3 rows
    }
}
