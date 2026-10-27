<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Http;

use Damoony\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testConstructDefaults(): void
    {
        $r = new Request('GET', '/');
        self::assertSame('GET', $r->method);
        self::assertSame('/', $r->path);
        self::assertSame([], $r->query);
        self::assertFalse($r->isPost());
        self::assertTrue($r->isGet());
    }

    public function testFromGlobalsCollectsData(): void
    {
        $backupServer = $_SERVER;
        $backupGet = $_GET;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/admin/login?x=1',
                'HTTP_HOST' => 'example.test',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'CONTENT_TYPE' => 'application/json',
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTPS' => 'on',
            ];
            $_GET = ['x' => '1'];

            $r = Request::fromGlobals();

            self::assertSame('POST', $r->method);
            self::assertSame('/admin/login', $r->path);
            self::assertSame('1', $r->query('x'));
            self::assertSame('example.test', $r->header('host'));
            self::assertSame('application/json', $r->header('content-type'));
            self::assertTrue($r->isAjax());
            self::assertTrue($r->isPost());
            self::assertFalse($r->isGet());
            self::assertSame('10.0.0.1', $r->ip());
            self::assertTrue($r->isHttps());
        } finally {
            $_SERVER = $backupServer;
            $_GET = $backupGet;
        }
    }

    public function testIsAjaxFalseWithoutHeader(): void
    {
        $r = new Request('GET', '/', server: ['REMOTE_ADDR' => '1.1.1.1']);
        self::assertFalse($r->isAjax());
        self::assertFalse($r->isHttps());
        self::assertSame('1.1.1.1', $r->ip());
    }

    public function testQueryAndPostAccessors(): void
    {
        $r = new Request('POST', '/', query: ['a' => 'b'], post: ['k' => 'v']);
        self::assertSame('b', $r->query('a'));
        self::assertNull($r->query('missing'));
        self::assertSame('v', $r->post('k'));
        self::assertSame('d', $r->post('missing', 'd'));
    }
}
