<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Http;

use Damoony\Http\Cookie;
use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
    }

    public function testGetReturnsStringOrNull(): void
    {
        $c = new Cookie();
        self::assertNull($c->get('missing'));

        $_COOKIE['a'] = 'x';
        self::assertSame('x', $c->get('a'));
    }
}