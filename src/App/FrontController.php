<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Domain\DomainResolverInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Routing\RouterInterface;
use Psr\Container\ContainerInterface;

final class FrontController
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function handle(Request $request): Response
    {
        $resolver = $this->container->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);

        $domain = $resolver->resolve((string) ($request->header('host') ?? $request->server['SERVER_NAME'] ?? ''));

        if (!$domain->available) {
            return Response::text('Maintenance: ' . ($domain->stub ?? 'unknown'), 503);
        }

        $router = $this->container->get(RouterInterface::class);
        assert($router instanceof RouterInterface);

        $match = $router->match($request->path, $domain);
        if (!$match->found) {
            return Response::notFound();
        }

        return Response::text(sprintf(
            "Damoony engine\npath: %s\nclass: %s\nargs: %s",
            $request->path,
            (string) $match->classChain,
            json_encode($match->args) ?: '{}',
        ));
    }
}