<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerTest extends TestCase
{
    public function testHasReturnsFalseForUnknown(): void
    {
        $c = new Container();
        self::assertFalse($c->has('nope'));
    }

    public function testGetThrowsNotFoundForUnknown(): void
    {
        $c = new Container();
        $this->expectException(NotFoundExceptionInterface::class);
        $c->get('nope');
    }

    public function testFactoryIsLazyAndCached(): void
    {
        $c = new Container();
        $calls = 0;
        $c->set('svc', function () use (&$calls) {
            $calls++;
            return new \stdClass();
        });

        self::assertSame(0, $calls);
        $a = $c->get('svc');
        $b = $c->get('svc');
        self::assertSame(1, $calls);
        self::assertSame($a, $b);
    }

    public function testSetInvalidatesInstance(): void
    {
        $c = new Container();
        $c->set('svc', fn () => 'a');
        self::assertSame('a', $c->get('svc'));

        $c->set('svc', fn () => 'b');
        self::assertSame('b', $c->get('svc'));
    }

    public function testSetInstance(): void
    {
        $c = new Container();
        $obj = new \stdClass();
        $c->setInstance('obj', $obj);
        self::assertTrue($c->has('obj'));
        self::assertSame($obj, $c->get('obj'));
    }

    public function testFactoryReceivesContainer(): void
    {
        $c = new Container();
        $c->setInstance('dep', 'value');
        $c->set('svc', fn (Container $inner) => $inner->get('dep') . '-x');
        self::assertSame('value-x', $c->get('svc'));
    }
}
