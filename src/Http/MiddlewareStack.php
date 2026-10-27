<?php

declare(strict_types=1);

namespace Damoony\Http;

use Damoony\Core\Container;

final class MiddlewareStack
{
    /** @var list<MiddlewareInterface|class-string<MiddlewareInterface>> */
    private array $middlewares = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /** @param callable(Request): Response $terminal */
    public function dispatch(Request $request, callable $terminal): Response
    {
        $next = $terminal;
        foreach (array_reverse($this->middlewares) as $middleware) {
            $resolved = $this->resolve($middleware);
            $prev = $next;
            $next = static fn (Request $r): Response => $resolved->process($r, $prev);
        }

        return $next($request);
    }

    private function resolve(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        $instance = $this->container->get($middleware);
        if (!$instance instanceof MiddlewareInterface) {
            throw new \RuntimeException("Service {$middleware} is not a MiddlewareInterface");
        }

        return $instance;
    }
}
