<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Auth\AuthInterface;
use Damoony\Core\Config;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Error\ErrorPage;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Routing\RouterInterface;
use Damoony\Security\Csrf;
use Damoony\Ui\Meta;
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

        $auth = $this->container->get(AuthInterface::class);
        assert($auth instanceof AuthInterface);

        if ($match->auth && !$auth->isAuthenticated()) {
            return Response::redirect('/login?return=' . urlencode($request->path), 302);
        }

        $csrf = $this->container->get(Csrf::class);
        assert($csrf instanceof Csrf);

        $paths = $this->container->get(Paths::class);
        assert($paths instanceof Paths);

        $site = $this->container->get('site');
        assert($site instanceof Config);

        $meta = new Meta();
        $context = new RegionContext($request, $domain, $meta, [
            'paths' => $paths,
            'site' => $site,
            'auth' => $auth,
            'csrf' => $csrf,
        ]);

        return $this->pages->handle($match, $context);
    }
}