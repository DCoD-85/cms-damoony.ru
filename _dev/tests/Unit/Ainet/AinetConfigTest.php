<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\AinetConfig;
use PHPUnit\Framework\TestCase;

final class AinetConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $c = AinetConfig::defaults();
        self::assertSame('wikimedia', $c->defaultProvider);
        self::assertSame(5, $c->connectTimeout);
        self::assertSame(20, $c->readTimeout);
        self::assertSame(10485760, $c->maxSize);
        self::assertSame(3, $c->maxRedirects);
        self::assertSame(30, $c->ratePerMin);
        self::assertSame(200, $c->minImageDimension);
        self::assertSame(7, $c->cacheTtlDays);
        self::assertTrue($c->blockPrivateIps);
        self::assertSame(['http', 'https'], $c->allowedSchemes);
        self::assertSame([80, 443], $c->allowedPorts);
        self::assertContains('api.openverse.engineering', $c->allowedDomains);
        self::assertContains('image/jpeg', $c->allowedMimes);
    }

    public function testFromArrayOverride(): void
    {
        $c = AinetConfig::fromArray([
            'allowed_domains' => ['api.github.com'],
            'providers' => [
                'default' => 'unsplash',
                'keys' => ['unsplash' => 'key1', 'pexels' => 'key2'],
            ],
            'timeouts' => ['connect' => 3, 'read' => 7],
            'limits' => ['max_size' => 1024, 'rate_per_min' => 10],
            'cache_ttl_days' => 1,
            'block_private_ips' => false,
        ]);

        self::assertSame(['api.github.com'], $c->allowedDomains);
        self::assertSame('unsplash', $c->defaultProvider);
        self::assertSame('key1', $c->unsplashKey);
        self::assertSame('key2', $c->pexelsKey);
        self::assertSame(3, $c->connectTimeout);
        self::assertSame(7, $c->readTimeout);
        self::assertSame(1024, $c->maxSize);
        self::assertSame(10, $c->ratePerMin);
        self::assertSame(1, $c->cacheTtlDays);
        self::assertFalse($c->blockPrivateIps);
    }

    public function testFromArrayEmptyDomainsFailSafe(): void
    {
        $c = AinetConfig::fromArray(['allowed_domains' => []]);
        self::assertSame([], $c->allowedDomains, 'Пустой whitelist = всё запрещено');
    }

    public function testFromArrayInvalidTypesIgnored(): void
    {
        $c = AinetConfig::fromArray([
            'allowed_domains' => 'not-an-array',
            'providers' => 'x',
            'timeouts' => 'y',
            'limits' => 'z',
        ]);
        // Дефолты для невалидных типов
        self::assertSame([], $c->allowedDomains);
        self::assertSame('openverse', $c->defaultProvider);
        self::assertSame(5, $c->connectTimeout);
    }

    public function testClampsNegativeValues(): void
    {
        $c = AinetConfig::fromArray([
            'timeouts' => ['connect' => -1, 'read' => 0],
            'limits' => ['max_size' => -100, 'max_redirects' => -5, 'rate_per_min' => 0],
            'cache_ttl_days' => -3,
        ]);
        self::assertSame(1, $c->connectTimeout);
        self::assertSame(1, $c->readTimeout);
        self::assertSame(1024, $c->maxSize);
        self::assertSame(0, $c->maxRedirects);
        self::assertSame(1, $c->ratePerMin);
        self::assertSame(0, $c->cacheTtlDays);
    }

    public function testFromFileMissingReturnsDefaults(): void
    {
        $c = AinetConfig::fromFile('/nonexistent/path/ainet.config');
        self::assertSame('wikimedia', $c->defaultProvider);
        self::assertNotEmpty($c->allowedDomains);
    }

    public function testFromFileInvalidJsonReturnsDefaults(): void
    {
        $file = sys_get_temp_dir() . '/ainet-test-' . bin2hex(random_bytes(4)) . '.config';
        file_put_contents($file, 'not json');
        try {
            $c = AinetConfig::fromFile($file);
            self::assertSame('wikimedia', $c->defaultProvider);
        } finally {
            @unlink($file);
        }
    }

    public function testFromFileValidJson(): void
    {
        $file = sys_get_temp_dir() . '/ainet-test-' . bin2hex(random_bytes(4)) . '.config';
        file_put_contents($file, json_encode([
            'allowed_domains' => ['api.github.com'],
            'providers' => ['default' => 'wikimedia'],
        ]));
        try {
            $c = AinetConfig::fromFile($file);
            self::assertSame(['api.github.com'], $c->allowedDomains);
            self::assertSame('wikimedia', $c->defaultProvider);
        } finally {
            @unlink($file);
        }
    }

    public function testSchemesFallbackWhenEmpty(): void
    {
        $c = AinetConfig::fromArray(['allowed_schemes' => []]);
        self::assertSame(['http', 'https'], $c->allowedSchemes);
    }

    public function testPortsFallbackWhenEmpty(): void
    {
        $c = AinetConfig::fromArray(['allowed_ports' => []]);
        self::assertSame([80, 443], $c->allowedPorts);
    }

    public function testMimesFallbackWhenEmpty(): void
    {
        $c = AinetConfig::fromArray(['allowed_mimes' => []]);
        self::assertContains('image/jpeg', $c->allowedMimes);
    }
}
