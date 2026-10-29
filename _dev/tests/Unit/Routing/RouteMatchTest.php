<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Routing;

use Damoony\Routing\RouteMatch;
use PHPUnit\Framework\TestCase;

final class RouteMatchTest extends TestCase
{
    public function testFoundCarriesContract(): void
    {
        $m = RouteMatch::found('algoritm_about', 'about', ['slug' => 'hello'], true);

        self::assertTrue($m->found);
        self::assertSame(200, $m->code);
        self::assertSame('algoritm_about', $m->classChain);
        self::assertSame('about', $m->pathRel);
        self::assertSame(['slug' => 'hello'], $m->args);
        self::assertTrue($m->auth);
    }

    public function testNotFoundDefaultsTo404(): void
    {
        $m = RouteMatch::notFound();

        self::assertFalse($m->found);
        self::assertSame(404, $m->code);
        self::assertNull($m->classChain);
        self::assertNull($m->pathRel);
        self::assertFalse($m->auth);
    }

    public function testNotFoundWithCustomCode(): void
    {
        self::assertSame(500, RouteMatch::notFound(500)->code);
        self::assertSame(403, RouteMatch::notFound(403)->code);
    }
}
