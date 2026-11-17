<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Observability\RequestId;
use PHPUnit\Framework\TestCase;

final class RequestIdTest extends TestCase
{
    protected function setUp(): void
    {
        RequestId::reset();
    }

    protected function tearDown(): void
    {
        RequestId::reset();
        unset($_SERVER['HTTP_X_REQUEST_ID']);
    }

    public function testGeneratesHexId(): void
    {
        $id = RequestId::init();

        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $id);
        self::assertSame($id, RequestId::get());
    }

    public function testAcceptsValidIncoming(): void
    {
        $incoming = 'abcdef0123456789';
        self::assertSame($incoming, RequestId::init($incoming));
        self::assertSame($incoming, RequestId::get());
    }

    public function testRejectsInvalidIncoming(): void
    {
        $id = RequestId::init('../../etc/passwd');

        self::assertNotSame('../../etc/passwd', $id);
        self::assertTrue(RequestId::isValid($id));
    }

    public function testInitFromGlobalsUsesHeader(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = '00112233445566aa';

        self::assertSame('00112233445566aa', RequestId::initFromGlobals());
    }

    public function testGetKeepsSameId(): void
    {
        $first = RequestId::get();
        $second = RequestId::get();

        self::assertSame($first, $second);
    }
}
