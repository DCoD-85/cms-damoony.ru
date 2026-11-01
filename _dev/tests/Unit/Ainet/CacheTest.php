<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\Cache;
use Damoony\Ainet\HttpResponse;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/damoony_ainet_cache_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->tmpDir);
    }

    private function cache(int $ttl = 604800): Cache
    {
        return new Cache($this->tmpDir, $ttl);
    }

    public function testGetMissingReturnsNull(): void
    {
        $c = $this->cache();
        self::assertNull($c->get('https://api.github.com/repos'));
    }

    public function testPutGetRoundtrip(): void
    {
        $c = $this->cache();
        $r = new HttpResponse(
            status: 200,
            headers: ['content-type' => 'application/json', 'x-custom' => 'v'],
            body: '{"ok":true}',
        );

        $c->put('https://api.github.com/repos', $r);
        $hit = $c->get('https://api.github.com/repos');

        self::assertNotNull($hit);
        self::assertSame(200, $hit->status);
        self::assertSame('application/json', $hit->mime());
        self::assertSame('{"ok":true}', $hit->body);
        self::assertSame('v', $hit->headers['x-custom']);
        self::assertFalse($hit->truncated);
    }

    public function testTruncatedFlagPreserved(): void
    {
        $c = $this->cache();
        $c->put('https://api.github.com/x', new HttpResponse(200, [], 'partial', truncated: true));

        $hit = $c->get('https://api.github.com/x');
        self::assertNotNull($hit);
        self::assertTrue($hit->truncated);
    }

    public function testDifferentUrlsDifferentEntries(): void
    {
        $c = $this->cache();
        $c->put('https://api.github.com/a', new HttpResponse(200, [], 'A'));
        $c->put('https://api.github.com/b', new HttpResponse(200, [], 'B'));

        self::assertSame('A', $c->get('https://api.github.com/a')?->body);
        self::assertSame('B', $c->get('https://api.github.com/b')?->body);
        self::assertNull($c->get('https://api.github.com/c'));
    }

    public function testExpiredEntryReturnsNull(): void
    {
        $c = new Cache($this->tmpDir, 1);
        $c->put('https://api.github.com/x', new HttpResponse(200, [], 'old'));

        // Перезаписываем timestamp в meta на 2 часа назад
        $entryDir = $this->tmpDir . '/' . hash('sha256', 'https://api.github.com/x');
        $metaFile = $entryDir . '/meta.json';
        $meta = json_decode((string) file_get_contents($metaFile), true);
        $meta['timestamp'] = time() - 7200;
        file_put_contents($metaFile, json_encode($meta));

        self::assertNull($c->get('https://api.github.com/x'));
    }

    public function testZeroTtlDisablesCache(): void
    {
        $c = $this->cache(0);
        $c->put('https://api.github.com/x', new HttpResponse(200, [], 'data'));

        self::assertNull($c->get('https://api.github.com/x'));
        self::assertSame(0, $c->stats()['entries'], 'put с ttl=0 ничего не пишет');
    }

    public function testClear(): void
    {
        $c = $this->cache();
        $c->put('https://api.github.com/a', new HttpResponse(200, [], 'A'));
        $c->put('https://api.github.com/b', new HttpResponse(200, [], 'B'));

        self::assertSame(2, $c->stats()['entries']);
        $removed = $c->clear();
        self::assertSame(2, $removed);
        self::assertSame(0, $c->stats()['entries']);
    }

    public function testStatsSize(): void
    {
        $c = $this->cache();
        $c->put('https://api.github.com/a', new HttpResponse(200, [], str_repeat('x', 1024)));

        $stats = $c->stats();
        self::assertSame(1, $stats['entries']);
        self::assertGreaterThanOrEqual(1024, $stats['size_bytes']);
        self::assertGreaterThan(0.0, $stats['size_mb']);
    }

    public function testUrlWithQueryString(): void
    {
        $c = $this->cache();
        $c->put('https://api.github.com/x?q=coffee&page=1', new HttpResponse(200, [], 'q1'));
        $c->put('https://api.github.com/x?q=coffee&page=2', new HttpResponse(200, [], 'q2'));

        self::assertSame('q1', $c->get('https://api.github.com/x?q=coffee&page=1')?->body);
        self::assertSame('q2', $c->get('https://api.github.com/x?q=coffee&page=2')?->body);
    }

    public function testCorruptedMetaReturnsNull(): void
    {
        $c = $this->cache();
        $c->put('https://api.github.com/x', new HttpResponse(200, [], 'data'));

        $metaFile = $this->tmpDir . '/' . hash('sha256', 'https://api.github.com/x') . '/meta.json';
        file_put_contents($metaFile, 'not-a-json');

        self::assertNull($c->get('https://api.github.com/x'));
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
}
