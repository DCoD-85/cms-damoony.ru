<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\AinetConfig;
use Damoony\Ainet\AinetFacade;
use Damoony\Ainet\Cache;
use Damoony\Ainet\DomainWhitelist;
use Damoony\Ainet\HttpClient;
use Damoony\Ainet\ImageSearch\OpenverseProvider;
use Damoony\Ainet\ImageSearch\WikimediaProvider;
use Damoony\Ainet\RateLimiter;
use Damoony\Ainet\UrlGuard;
use PHPUnit\Framework\TestCase;

final class AinetFacadeTest extends TestCase
{
    private string $cacheDir;
    private FakeTransport $transport;
    private AinetConfig $config;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/damoony_ainet_facade_' . bin2hex(random_bytes(4));
        $this->transport = new FakeTransport();
        $this->config = AinetConfig::fromArray([
            'allowed_domains' => [
                'api.openverse.org',
                'api.openverse.engineering',
                'commons.wikimedia.org',
                'upload.wikimedia.org',
                'ru.wikipedia.org',
                'api.github.com',
            ],
            'providers' => ['default' => 'openverse'], // явно, тесты не зависят от defaults
            'cache_ttl_days' => 7,
            'limits' => ['min_image_dimension' => 200],
        ]);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->cacheDir);
        $this->rmrf($this->cacheDir . '-rl');
    }

    private function facade(): AinetFacade
    {
        $whitelist = new DomainWhitelist($this->config->allowedDomains);
        $guard = new UrlGuard(
            $whitelist,
            $this->config->allowedSchemes,
            $this->config->allowedPorts,
            $this->config->blockPrivateIps,
        );
        $http = new HttpClient(
            $guard,
            $this->transport,
            $this->config->connectTimeout,
            $this->config->readTimeout,
            $this->config->maxSize,
            $this->config->maxRedirects,
        );
        $cache = new Cache($this->cacheDir, $this->config->cacheTtlDays * 86400);

        return new AinetFacade($this->config, $http, $cache, [
            'openverse' => new OpenverseProvider($http),
            'wikimedia' => new WikimediaProvider($http),
        ]);
    }

    private function facadeWithRateLimit(int $maxPerMin): AinetFacade
    {
        $whitelist = new DomainWhitelist($this->config->allowedDomains);
        $guard = new UrlGuard(
            $whitelist,
            $this->config->allowedSchemes,
            $this->config->allowedPorts,
            $this->config->blockPrivateIps,
        );
        $http = new HttpClient(
            $guard,
            $this->transport,
            $this->config->connectTimeout,
            $this->config->readTimeout,
            $this->config->maxSize,
            $this->config->maxRedirects,
        );
        $cache = new Cache($this->cacheDir, $this->config->cacheTtlDays * 86400);

        // Изолированный per-test каталог для лимитера (не пересекается между прогонами)
        $rlDir = $this->cacheDir . '-rl';
        if (!is_dir($rlDir)) {
            mkdir($rlDir, 0o777, true);
        }
        $rl = new RateLimiter($rlDir . '/ainet-ratelimit.json', $maxPerMin, 60);

        return new AinetFacade($this->config, $http, $cache, [
            'openverse' => new OpenverseProvider($http),
            'wikimedia' => new WikimediaProvider($http),
        ], $rl);
    }

    /** @return array{bytes: string, width: int, height: int} */
    private function makePng(int $width = 200, int $height = 200): array
    {
        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            self::markTestSkipped('GD not available');
        }
        $bg = imagecolorallocate($im, 200, 100, 50);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $bg);

        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return ['bytes' => $bytes, 'width' => $width, 'height' => $height];
    }

    // === status ===

    public function testStatus(): void
    {
        $result = $this->facade()->status();
        self::assertTrue($result['ok']);
        self::assertSame(['openverse', 'wikimedia'], $result['data']['providers']);
        self::assertSame('openverse', $result['data']['default_provider']);
        self::assertSame(6, $result['data']['whitelist_count']);
        self::assertSame(0, $result['data']['cache_entries']);
        self::assertSame(30, $result['data']['rate_limit']['max']);
    }

    // === searchImages ===

    public function testSearchImagesUnknownProvider(): void
    {
        $result = $this->facade()->searchImages('coffee', 5, 'nonexistent');
        self::assertFalse($result['ok']);
        self::assertStringContainsString('Unknown provider', $result['error']);
        self::assertStringContainsString('openverse', $result['hint']);
    }

    public function testSearchImagesOpenverseSuccess(): void
    {
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode([
                'results' => [
                    [
                        'url' => 'https://upload.wikimedia.org/c1.jpg',
                        'thumbnail' => 'https://upload.wikimedia.org/t1.jpg',
                        'width' => 1200,
                        'height' => 800,
                        'license' => 'cc0',
                        'creator' => 'Alice',
                    ],
                ],
            ]),
        ]);

        $r = $this->facade()->searchImages('coffee', 5);
        self::assertTrue($r['ok']);
        self::assertSame('openverse', $r['data']['provider']);
        self::assertCount(1, $r['data']['results']);
        self::assertSame('https://upload.wikimedia.org/c1.jpg', $r['data']['results'][0]['url']);
    }

    public function testSearchImagesProviderError(): void
    {
        $this->transport->queueResponse([
            'status' => 503,
            'headers' => [],
            'body' => 'error',
        ]);
        // Явный provider → без auto-fallback
        $r = $this->facade()->searchImages('coffee', 5, 'openverse');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('503', $r['error']);
        self::assertSame('openverse', $r['context']['provider']);
    }

    public function testSearchImagesFallbackToWikimedia(): void
    {
        // Openverse (default) → 503, потом fallback на wikimedia → 200
        $this->transport->queueResponse([
            'status' => 503,
            'headers' => [],
            'body' => 'openverse down',
        ]);
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode([
                'query' => [
                    'pages' => [
                        '1' => [
                            'title' => 'File:Coffee.jpg',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/coffee.jpg',
                                'thumburl' => 'https://upload.wikimedia.org/coffee_thumb.jpg',
                                'width' => 1200,
                                'height' => 800,
                                'extmetadata' => [
                                    'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $r = $this->facade()->searchImages('coffee', 5);
        self::assertTrue($r['ok']);
        self::assertSame('wikimedia', $r['data']['provider']);
        self::assertSame('openverse', $r['data']['fallback_from']);
        self::assertCount(1, $r['data']['results']);
    }

    // === fetchJson ===

    public function testFetchJsonSuccess(): void
    {
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"title":"Coffee","extract":"..."}',
        ]);

        $r = $this->facade()->fetchJson('https://ru.wikipedia.org/api/rest_v1/page/summary/Кофе');
        self::assertTrue($r['ok']);
        self::assertSame('Coffee', $r['data']['json']['title']);
        self::assertSame(200, $r['data']['status']);
        self::assertFalse($r['data']['cached']);
    }

    public function testFetchJsonCachedOnSecondCall(): void
    {
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"x":1}',
        ]);
        $facade = $this->facade();
        $url = 'https://ru.wikipedia.org/api/x';

        $r1 = $facade->fetchJson($url);
        $r2 = $facade->fetchJson($url);

        self::assertTrue($r1['ok']);
        self::assertFalse($r1['data']['cached']);
        self::assertTrue($r2['ok']);
        self::assertTrue($r2['data']['cached'], 'Второй вызов — из кэша');
        self::assertCount(1, $this->transport->getRequestedUrls(), 'HTTP вызван один раз');
    }

    public function testFetchJsonInvalidJson(): void
    {
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'text/html'],
            'body' => 'not-json',
        ]);

        $r = $this->facade()->fetchJson('https://ru.wikipedia.org/x');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Invalid JSON', $r['error']);
    }

    public function testFetchJsonHttpError(): void
    {
        $this->transport->queueResponse([
            'status' => 404,
            'headers' => [],
            'body' => 'not found',
        ]);

        $r = $this->facade()->fetchJson('https://ru.wikipedia.org/x');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('404', $r['error']);
        self::assertSame(404, $r['context']['status']);
    }

    public function testFetchJsonNotWhitelisted(): void
    {
        $r = $this->facade()->fetchJson('https://evil.com/x');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('whitelist', strtolower($r['error']));
    }

    // === fetchText ===

    public function testFetchTextSuccess(): void
    {
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'text/plain'],
            'body' => 'Hello, world',
        ]);

        $r = $this->facade()->fetchText('https://ru.wikipedia.org/x');
        self::assertTrue($r['ok']);
        self::assertSame('Hello, world', $r['data']['text']);
        self::assertSame(12, $r['data']['size']);
        self::assertFalse($r['data']['cached']);
    }

    // === downloadImage ===

    public function testDownloadImageValidatesDomain(): void
    {
        $r = $this->facade()->downloadImage('https://upload.wikimedia.org/x.jpg', 'a..ru', 'www', 'cat');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Invalid domain', $r['error']);
    }

    public function testDownloadImageValidatesAllias(): void
    {
        $r = $this->facade()->downloadImage('https://upload.wikimedia.org/x.jpg', 'example.ru', 'Bad-Name', 'cat');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Invalid allias', $r['error']);
    }

    public function testDownloadImageValidatesCategory(): void
    {
        $r = $this->facade()->downloadImage('https://upload.wikimedia.org/x.jpg', 'example.ru', 'www', 'bad category!');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Invalid category', $r['error']);
    }

    public function testDownloadImageSuccess(): void
    {
        $png = $this->makePng(300, 400);
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'image/png'],
            'body' => $png['bytes'],
        ]);

        $r = $this->facade()->downloadImage(
            'https://upload.wikimedia.org/img.png',
            'example.ru',
            'www',
            'coffee',
            'Coffee cup',
        );

        self::assertTrue($r['ok'], (string) json_encode($r));
        self::assertSame($png['bytes'], $r['data']['bytes'], 'Bytes должны совпадать');
        self::assertSame('image/png', $r['data']['mime']);
        self::assertSame(300, $r['data']['width']);
        self::assertSame(400, $r['data']['height']);
        self::assertSame('png', $r['data']['ext']);
        self::assertSame(16, strlen($r['data']['hash']));
        self::assertFalse($r['data']['cached']);
        self::assertSame('Coffee cup', $r['data']['meta']['alt']);
        self::assertSame('https://upload.wikimedia.org/img.png', $r['data']['meta']['source_url']);
    }

    public function testDownloadImageTooSmall(): void
    {
        $png = $this->makePng(50, 50);
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'image/png'],
            'body' => $png['bytes'],
        ]);

        $r = $this->facade()->downloadImage('https://upload.wikimedia.org/img.png', 'example.ru', 'www', 'cat');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('too small', $r['error']);
    }

    public function testDownloadImageWrongMime(): void
    {
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/pdf'],
            'body' => '%PDF-1.4...',
        ]);

        $r = $this->facade()->downloadImage('https://upload.wikimedia.org/x.pdf', 'example.ru', 'www', 'cat');
        self::assertFalse($r['ok']);
        self::assertStringContainsString("Blocked MIME 'application/pdf'", $r['error']);
    }

    public function testDownloadImageHttpError(): void
    {
        $this->transport->queueResponse(['status' => 404, 'headers' => [], 'body' => '']);

        $r = $this->facade()->downloadImage('https://upload.wikimedia.org/x.jpg', 'example.ru', 'www', 'cat');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('404', $r['error']);
    }

    public function testDownloadImageNotWhitelisted(): void
    {
        $r = $this->facade()->downloadImage('https://evil.com/x.jpg', 'example.ru', 'www', 'cat');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('whitelist', strtolower($r['error']));
    }

    public function testDownloadImageCached(): void
    {
        $png = $this->makePng(200, 200);
        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'image/png'],
            'body' => $png['bytes'],
        ]);
        $facade = $this->facade();
        $url = 'https://upload.wikimedia.org/cached.png';

        $r1 = $facade->downloadImage($url, 'example.ru', 'www', 'cat');
        $r2 = $facade->downloadImage($url, 'example.ru', 'www', 'cat');

        self::assertTrue($r1['ok']);
        self::assertFalse($r1['data']['cached']);
        self::assertTrue($r2['ok']);
        self::assertTrue($r2['data']['cached']);
        self::assertSame($r1['data']['hash'], $r2['data']['hash']);
        self::assertCount(1, $this->transport->getRequestedUrls());
    }

    // === rate limit ===

    public function testRateLimitBlocksSecondFetch(): void
    {
        $facade = $this->facadeWithRateLimit(1);

        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"ok":true}',
        ]);

        $first = $facade->fetchJson('https://api.github.com/x');
        self::assertTrue($first['ok']);

        // Второй вызов к другому URL — блокируется лимитом до HTTP
        $second = $facade->fetchJson('https://api.github.com/y');
        self::assertFalse($second['ok']);
        self::assertSame('Rate limit exceeded', $second['error']);
        self::assertStringContainsString('Wait ', $second['hint']);
        self::assertStringContainsString('limit: 1/min', $second['hint']);
    }

    public function testRateLimitDoesNotAffectCacheHit(): void
    {
        $facade = $this->facadeWithRateLimit(1);

        $this->transport->queueResponse([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"ok":true}',
        ]);

        $first = $facade->fetchJson('https://api.github.com/x');
        self::assertTrue($first['ok']);
        self::assertFalse($first['data']['cached']);

        // Второй вызов к тому же URL — кэш-хит, лимит не тратится
        $second = $facade->fetchJson('https://api.github.com/x');
        self::assertTrue($second['ok']);
        self::assertTrue($second['data']['cached']);
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
