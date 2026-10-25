<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Domain\DomainResolverInterface;
use Damoony\Error\ErrorPage;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Routing\RouterInterface;
use Psr\Container\ContainerInterface;

final class FrontController
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly PageHandlerInterface $pages,
    ) {
    }

    public function handle(Request $request): Response
    {
        $resolver = $this->container->get(DomainResolverInterface::class);
        assert($resolver instanceof DomainResolverInterface);

        $host = (string) ($request->header('host') ?? $request->server['SERVER_NAME'] ?? '');
        $domain = $resolver->resolve($host);

        if (!$domain->available) {
            return ErrorPage::maintenance($domain->language);
        }

        $router = $this->container->get(RouterInterface::class);
        assert($router instanceof RouterInterface);

        $match = $router->match($request->path, $domain);
        if (!$match->found) {
            return $match->code === 403
                ? ErrorPage::forbidden($domain->language)
                : ErrorPage::notFound($domain->language);
        }

        $context = new RegionContext($request, $domain);

        return $this->pages->handle($match, $context);
    }
}