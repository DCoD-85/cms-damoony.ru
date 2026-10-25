<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Session;

use Damoony\Session\NativeSession;
use PHPUnit\Framework\TestCase;

final class NativeSessionTest extends TestCase
{
    public function testSetGetRemove(): void
    {
        $s = new NativeSession('TEST_' . bin2hex(random_bytes(4)));
        $s->start();
        self::assertTrue($s->isStarted());

        $s->set('k', 'v');
        self::assertSame('v', $s->get('k'));

        $s->remove('k');
        self::assertNull($s->get('k'));
        self::assertSame('d', $s->get('k', 'd'));

        $s->destroy();
    }

    public function testRegenerateKeepsData(): void
    {
        $s = new NativeSession('TEST_' . bin2hex(random_bytes(4)));
        $s->start();
        $s->set('k', 'v');
        $before = session_id();
        $s->regenerate();

        self::assertNotSame($before, session_id());
        self::assertSame('v', $s->get('k'));

        $s->destroy();
    }
}