<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Ainet;

use Damoony\Ainet\DomainWhitelist;
use PHPUnit\Framework\TestCase;

final class DomainWhitelistTest extends TestCase
{
    public function testAllowsExactMatch(): void
    {
        $w = new DomainWhitelist(['api.github.com', 'ru.wikipedia.org']);
        self::assertTrue($w->isAllowed('api.github.com'));
        self::assertTrue($w->isAllowed('ru.wikipedia.org'));
    }

    public function testCaseInsensitive(): void
    {
        $w = new DomainWhitelist(['API.GitHub.com']);
        self::assertTrue($w->isAllowed('api.github.com'));
        self::assertTrue($w->isAllowed('API.GITHUB.COM'));
    }

    public function testRejectsUnknown(): void
    {
        $w = new DomainWhitelist(['api.github.com']);
        self::assertFalse($w->isAllowed('evil.com'));
        self::assertFalse($w->isAllowed(''));
    }

    public function testRejectsSubdomainMismatch(): void
    {
        $w = new DomainWhitelist(['example.com']);
        self::assertFalse($w->isAllowed('api.example.com'), 'Точный матч, без wildcard');
        self::assertFalse($w->isAllowed('sub.example.com'));
    }

    public function testRejectsSuffixAttack(): void
    {
        $w = new DomainWhitelist(['api.github.com']);
        self::assertFalse($w->isAllowed('api.github.com.evil.com'));
        self::assertFalse($w->isAllowed('evilapi.github.com'));
    }

    public function testRejectsInvalidDomainNames(): void
    {
        $w = new DomainWhitelist([
            '', 'not a domain', 'a..b', '-bad.com', 'bad-.com', 'http://x.com',
        ]);
        self::assertSame(0, $w->count(), 'Невалидные записи игнорируются');
    }

    public function testFiltersInvalidKeepValid(): void
    {
        $w = new DomainWhitelist(['api.github.com', 'bad host', 'ru.wikipedia.org']);
        self::assertSame(2, $w->count());
        self::assertTrue($w->isAllowed('api.github.com'));
        self::assertTrue($w->isAllowed('ru.wikipedia.org'));
    }

    public function testFromConfig(): void
    {
        $w = DomainWhitelist::fromConfig([
            'allowed_domains' => ['api.openverse.engineering', 'commons.wikimedia.org'],
        ]);
        self::assertSame(2, $w->count());
        self::assertTrue($w->isAllowed('api.openverse.engineering'));
    }

    public function testFromConfigEmpty(): void
    {
        $w = DomainWhitelist::fromConfig([]);
        self::assertSame(0, $w->count());
        self::assertFalse($w->isAllowed('api.github.com'), 'Пустой whitelist = всё запрещено');
    }

    public function testFromConfigNotArray(): void
    {
        $w = DomainWhitelist::fromConfig(['allowed_domains' => 'not-array']);
        self::assertSame(0, $w->count());
    }

    public function testAllReturnsList(): void
    {
        $w = new DomainWhitelist(['api.github.com', 'ru.wikipedia.org']);
        $all = $w->all();
        sort($all);
        self::assertSame(['api.github.com', 'ru.wikipedia.org'], $all);
    }
}
