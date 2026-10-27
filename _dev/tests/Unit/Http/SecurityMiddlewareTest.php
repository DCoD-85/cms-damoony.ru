<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Http;

use Damoony\Http\Middleware\RateLimitMiddleware;
use Damoony\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Http\Request;
use Damoony\Http\Response;
use PHPUnit\Framework\TestCase;

final class SecurityMiddlewareTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/damoony_rl_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testSecurityHeadersDefault(): void
    {
        $mw = new SecurityHeadersMiddleware();
        $r = $mw->process(new Request('GET', '/'), fn () => Response::text('ok'));

        self::assertSame('nosniff', $r->headers['x-content-type-options']);
        self::assertSame('DENY', $r->headers['x-frame-options']);
        self::assertSame('same-origin', $r->headers['referrer-policy']);
        self::assertArrayHasKey('content-security-policy', $r->headers);
        self::assertArrayHasKey('strict-transport-security', $r->headers);
    }

    public function testSecurityHeadersNoHstsWhenNotHttps(): void
    {
        $mw = new SecurityHeadersMiddleware(false);
        $r = $mw->process(new Request('GET', '/'), fn () => Response::text('ok'));
        self::assertArrayNotHasKey('strict-transport-security', $r->headers);
    }

    public function testRateLimitAllowsUpToMax(): void
    {
        $mw = new RateLimitMiddleware($this->dir, max: 3, window: 60, ban: 900);
        $req = new Request('GET', '/', server: ['REMOTE_ADDR' => '10.0.0.1']);

        for ($i = 0; $i < 3; $i++) {
            $r = $mw->process($req, fn () => Response::text('ok'));
            self::assertSame(200, $r->status);
        }
    }

    public function testRateLimitBlocksAfterMax(): void
    {
        $mw = new RateLimitMiddleware($this->dir, max: 2, window: 60, ban: 900);
        $req = new Request('GET', '/', server: ['REMOTE_ADDR' => '10.0.0.2']);

        $mw->process($req, fn () => Response::text('ok'));
        $mw->process($req, fn () => Response::text('ok'));
        $r = $mw->process($req, fn () => Response::text('ok'));

        self::assertSame(429, $r->status);
        self::assertArrayHasKey('retry-after', $r->headers);
    }

    public function testRateLimitBlocksWhenBanned(): void
    {
        $mw = new RateLimitMiddleware($this->dir, max: 1, window: 60, ban: 900);
        $req = new Request('GET', '/', server: ['REMOTE_ADDR' => '10.0.0.3']);

        $mw->process($req, fn () => Response::text('ok'));
        $mw->process($req, fn () => Response::text('ok'));

        $r = $mw->process($req, fn () => Response::text('ok'));
        self::assertSame(429, $r->status);
    }

    public function testRateLimitWithoutIpSkips(): void
    {
        $mw = new RateLimitMiddleware($this->dir, max: 1, window: 60, ban: 900);
        $req = new Request('GET', '/');

        $r = $mw->process($req, fn () => Response::text('ok'));
        self::assertSame(200, $r->status);
    }
}
