<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Algoritm\AlgoritmInterface;
use Damoony\Core\Config;
use Damoony\Domain\DomainContext;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionLoader;
use Damoony\Routing\RouteMatch;
use Damoony\Ui\Document;
use Damoony\Ui\Node;
use RuntimeException;

final class PageHandler implements PageHandlerInterface
{
    public function __construct(
        private readonly string $appRoot,
        private readonly DomainResolverInterface $resolver,
    ) {
    }

    public function handle(RouteMatch $match, RegionContext $context): Response
    {
        if ($match->classChain === null || $match->pathRel === null) {
            return Response::notFound();
        }

        $domain = $context->domain;
        $file = $this->path($domain, $match->pathRel, $match->classChain);
        if (!is_file($file)) {
            return Response::notFound();
        }

        require_once $file;

        $ns = $this->resolver->namespace($domain->real);
        $class = $ns . '\\' . $domain->allias . '\\' . $match->classChain;
        if (!class_exists($class, false)) {
            return Response::internalError('Page class not found: ' . $class);
        }

        if (!is_subclass_of($class, AlgoritmInterface::class)) {
            return Response::internalError('Page class does not implement AlgoritmInterface');
        }

        $paths = $context->service('paths');
        $site = $context->service('site');

        if (!$paths instanceof Paths) {
            return Response::internalError('Paths service missing');
        }

        $loader = new RegionLoader(
            $paths,
            $site instanceof Config ? $site : null,
        );
        $regions = $loader->load($domain);

        try {
            /** @var AlgoritmInterface $page */
            $page = new $class($context, $regions, $match->args);
            $result = $page->default();

            return $this->toResponse($result, $domain, $context);
        } catch (\Throwable $e) {
            return Response::internalError($e->getMessage());
        }
    }

    private function path(DomainContext $domain, string $pathRel, string $classChain): string
    {
        return rtrim($this->appRoot, '/') . '/app/' . $domain->real
            . '/' . $domain->allias
            . '/algoritm'
            . ($pathRel === '' ? '' : '/' . $pathRel)
            . '/' . $classChain . '.php';
    }

    private function toResponse(Node|Response|null $result, DomainContext $domain, RegionContext $context): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof Node) {
            $doc = new Document($context->meta);
            $doc->lang($domain->language);
            $doc->body()->append($result);
            return Response::html($doc->release());
        }

        throw new RuntimeException('Page returned null; default() must return Node or Response');
    }
}
