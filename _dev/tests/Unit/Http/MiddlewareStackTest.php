<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Http;

use Damoony\Core\Container;
use Damoony\Http\MiddlewareInterface;
use Damoony\Http\MiddlewareStack;
use Damoony\Http\Request;
use Damoony\Http\Response;
use PHPUnit\Framework\TestCase;

final class MiddlewareStackTest extends TestCase
{
    public function testEmptyStackCallsTerminal(): void
    {
        $stack = new MiddlewareStack(new Container());
        $called = false;

        $response = $stack->dispatch(new Request('GET', '/'), function (Request $r) use (&$called): Response {
            $called = true;
            return Response::text($r->path);
        });

        self::assertTrue($called);
        self::assertSame('/', $response->body);
    }

    public function testOrderIsOutsideIn(): void
    {
        $stack = new MiddlewareStack(new Container());
        $log = [];

        $mk = fn (string $name) => new class($name, $log) implements MiddlewareInterface {
            /** @param list<string> $log */
            public function __construct(private string $name, private array &$log)
            {
            }

            public function process(Request $request, callable $next): Response
            {
                $this->log[] = "before:{$this->name}";
                $response = $next($request);
                $this->log[] = "after:{$this->name}";
                return $response;
            }
        };

        $stack->add($mk('a'))->add($mk('b'))->add($mk('c'));

        $stack->dispatch(new Request('GET', '/'), fn (): Response => Response::text('x'));

        self::assertSame(
            ['before:a', 'before:b', 'before:c', 'after:c', 'after:b', 'after:a'],
            $log,
        );
    }

    public function testStringIsResolvedFromContainer(): void
    {
        $container = new Container();
        $container->setInstance('mw', new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                return $next($request)->withHeader('x-mw', 'on');
            }
        });

        $stack = new MiddlewareStack($container);
        $stack->add('mw');

        $response = $stack->dispatch(new Request('GET', '/'), fn (): Response => Response::text('x'));

        self::assertSame('on', $response->headers['x-mw']);
    }

    public function testNonMiddlewareFromContainerThrows(): void
    {
        $container = new Container();
        $container->setInstance('bad', new \stdClass());

        $stack = new MiddlewareStack($container);
        $stack->add('bad');

        $this->expectException(\RuntimeException::class);
        $stack->dispatch(new Request('GET', '/'), fn (): Response => Response::text('x'));
    }

    public function testJsonContentTypeMiddleware(): void
    {
        $mw = new \Damoony\Http\Middleware\JsonContentTypeMiddleware();
        $req = new Request('GET', '/', headers: ['x-requested-with' => 'XMLHttpRequest']);

        $r1 = $mw->process($req, fn (): Response => Response::text('x'));
        self::assertSame('application/json; charset=utf-8', $r1->headers['content-type']);

        $r2 = $mw->process(new Request('GET', '/'), fn (): Response => Response::text('x'));
        self::assertSame('text/plain; charset=utf-8', $r2->headers['content-type']);
    }
}