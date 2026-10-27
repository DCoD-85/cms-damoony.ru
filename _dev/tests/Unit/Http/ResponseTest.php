<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Http;

use Damoony\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDefaults(): void
    {
        $r = new Response();
        self::assertSame('', $r->body);
        self::assertSame(200, $r->status);
        self::assertSame('text/html; charset=utf-8', $r->headers['content-type']);
    }

    public function testHtml(): void
    {
        $r = Response::html('<p>hi</p>', 201);
        self::assertSame(201, $r->status);
        self::assertSame('<p>hi</p>', $r->body);
        self::assertSame('text/html; charset=utf-8', $r->headers['content-type']);
    }

    public function testJson(): void
    {
        $r = Response::json(['a' => 1, 's' => 'тест'], 202);
        self::assertSame(202, $r->status);
        self::assertSame('application/json; charset=utf-8', $r->headers['content-type']);
        self::assertSame('{"a":1,"s":"тест"}', $r->body);
    }

    public function testJsonEncodingFailure(): void
    {
        $bad = "\xB1\x31";
        $r = Response::json(['bad' => $bad]);
        self::assertSame('{}', $r->body);
    }

    public function testText(): void
    {
        $r = Response::text('hi', 204);
        self::assertSame(204, $r->status);
        self::assertSame('text/plain; charset=utf-8', $r->headers['content-type']);
    }

    public function testRedirect(): void
    {
        $r = Response::redirect('/login', 307);
        self::assertSame(307, $r->status);
        self::assertSame('/login', $r->headers['location']);
    }

    public function testNotFound(): void
    {
        $r = Response::notFound();
        self::assertSame(404, $r->status);
    }

    public function testInternalError(): void
    {
        $r = Response::internalError();
        self::assertSame(500, $r->status);
    }

    public function testWithHeaderIsImmutable(): void
    {
        $a = Response::text('x');
        $b = $a->withHeader('X-Foo', 'bar');

        self::assertNotSame($a, $b);
        self::assertArrayNotHasKey('x-foo', $a->headers);
        self::assertSame('bar', $b->headers['x-foo']);
    }
}
