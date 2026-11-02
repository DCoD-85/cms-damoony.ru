<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\DomainWhitelist;
use Damoony\Ainet\UrlGuard;
use Damoony\Ainet\UrlGuardException;
use PHPUnit\Framework\TestCase;

final class UrlGuardTest extends TestCase
{
    private function guard(bool $blockPrivate = true): UrlGuard
    {
        $w = new DomainWhitelist([
            'api.github.com',
            'ru.wikipedia.org',
            'api.openverse.engineering',
            'images.unsplash.com',
        ]);

        return new UrlGuard($w, ['http', 'https'], [80, 443], $blockPrivate);
    }

    public function testValidHttpsUrl(): void
    {
        $r = $this->guard()->validate('https://api.github.com/repos');
        self::assertSame('https://api.github.com/repos', $r['url']);
        self::assertSame('api.github.com', $r['host']);
        self::assertSame('https', $r['scheme']);
    }

    public function testValidHttpWithPort(): void
    {
        $r = $this->guard()->validate('http://ru.wikipedia.org:80/wiki');
        self::assertSame('http', $r['scheme']);
        self::assertSame(80, $r['port']);
    }

    public function testRejectsEmptyUrl(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('');
    }

    public function testRejectsMalformedUrl(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('not a url');
    }

    public function testRejectsFileScheme(): void
    {
        try {
            $this->guard()->validate('file:///etc/passwd');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('scheme', strtolower($e->getMessage()));
        }
    }

    public function testRejectsFtpScheme(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('ftp://api.github.com/x');
    }

    public function testRejectsUserPass(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('https://user:pass@api.github.com/x');
    }

    public function testRejectsBlockedPort(): void
    {
        try {
            $this->guard()->validate('https://api.github.com:22/x');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('port', strtolower($e->getMessage()));
        }
    }

    public function testRejectsUnknownDomain(): void
    {
        try {
            $this->guard()->validate('https://evil.com/x');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('whitelist', strtolower($e->getMessage()));
        }
    }

    public function testRejectsSuffixAttack(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('https://api.github.com.evil.com/x');
    }

    public function testRejectsLoopback(): void
    {
        try {
            $this->guard()->validate('http://127.0.0.1/admin');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('private', strtolower($e->getMessage()));
        }
    }

    public function testRejectsPrivateIp(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('http://192.168.1.1/');
    }

    public function testRejectsAwsMetadata(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('http://169.254.169.254/latest/meta-data');
    }

    public function testRejectsLongQuery(): void
    {
        $longQuery = str_repeat('a', 3000);
        $this->expectException(UrlGuardException::class);
        $this->guard()->validate('https://api.github.com/x?' . $longQuery);
    }

    public function testStripsFragment(): void
    {
        $r = $this->guard()->validate('https://api.github.com/x#fragment');
        self::assertSame('https://api.github.com/x', $r['url']);
    }

    public function testBlockPrivateIpFlagDoesNotBypassWhitelist(): void
    {
        // Даже с blockPrivateIps=false — whitelist остаётся обязательным.
        // DomainWhitelist намеренно не принимает IP (regex [a-z]{2,} в TLD),
        // это дополнительная защита: даже если IP попадёт в whitelist — отфильтруется.
        $this->expectException(UrlGuardException::class);
        $this->expectExceptionMessageMatches('/whitelist/i');
        $this->guard(false)->validate('http://127.0.0.1/x');
    }

    public function testBlockPrivateIpFlagDoesNotAffectValidUrls(): void
    {
        $r = $this->guard(false)->validate('https://api.github.com/x');
        self::assertSame('api.github.com', $r['host']);
        self::assertSame('https', $r['scheme']);
    }

    public function testRejectsBracketedIpv6Loopback(): void
    {
        // parse_url keeps brackets: host='[::1]'. Before fix:
        // filter_var('[::1]') returned false -> step 7 skipped ->
        // only whitelist saved us. After fix: isPrivateIp catches.
        try {
            $this->guard()->validate('http://[::1]/admin');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('private', strtolower($e->getMessage()));
        }
    }

    public function testRejectsPrivateIpv6(): void
    {
        // fc00::/7 — ULA (private), fe80::/10 — link-local.
        foreach (['http://[fc00::1]/', 'http://[fe80::1]/'] as $url) {
            try {
                $this->guard()->validate($url);
                self::fail("Ожидалось исключение для {$url}");
            } catch (UrlGuardException $e) {
                self::assertStringContainsString('private', strtolower($e->getMessage()));
            }
        }
    }

    public function testRejectsIpv4MappedIpv6(): void
    {
        // ::ffff:127.0.0.1 is IPv4-mapped loopback.
        try {
            $this->guard()->validate('http://[::ffff:127.0.0.1]/');
            self::fail('Ожидалось исключение');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('private', strtolower($e->getMessage()));
        }
    }

    public function testDnsFailureFailsClosed(): void
    {
        // .invalid is a reserved TLD (RFC 2606); never resolves.
        // Add it to whitelist to reach assertNotPrivateIp.
        $w = new DomainWhitelist(['api.github.com', 'nonexistent-abc.invalid']);
        $guard = new UrlGuard($w, ['http', 'https'], [80, 443], true);

        try {
            $guard->validate('https://nonexistent-abc.invalid/x');
            self::fail('Ожидалось исключение (DNS fail-closed)');
        } catch (UrlGuardException $e) {
            self::assertStringContainsString('dns', strtolower($e->getMessage()));
        }
    }
}
