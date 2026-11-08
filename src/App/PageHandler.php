<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Algoritm\PageInterface;
use Damoony\Core\Config;
use Damoony\Core\Env;
use Damoony\Domain\DomainContext;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Error\ErrorPage;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionLoader;
use Damoony\Routing\RouteMatch;
use Damoony\Ui\Document;
use Damoony\Ui\Node;

final class PageHandler implements PageHandlerInterface
{
    public function __construct(
        private readonly string $appRoot,
        private readonly DomainResolverInterface $resolver,
    ) {
    }

    public function handle(RouteMatch $match, RegionContext $context, ?string $adminRole = null): Response
    {
        $sessionIdRaw = $context->service('session_id');
        $sessionId = is_string($sessionIdRaw) && $sessionIdRaw !== '' ? $sessionIdRaw : null;
        if ($match->classChain === null || $match->pathRel === null) {
            return Response::notFound();
        }

        $domain = $context->domain;
        $file = $this->path($domain, $match->pathRel, $match->classChain, $sessionId, $adminRole);
        if (!is_file($file)) {
            return Response::notFound();
        }

        if ($adminRole !== null) {
            $class = 'Damoony\\Admin\\Pages\\' . $adminRole . '\\' . $match->classChain;
        } else {
            $ns = $this->resolver->namespace($domain->real);
            $class = $ns . '\\' . $domain->allias . '\\' . $match->classChain;
        }

        if (!class_exists($class, false)) {
            require_once $file;
        }

        if (!class_exists($class, false)) {
            return ErrorPage::serverError($domain->language, $this->debugDetail('Page class not found: ' . $class));
        }

        if (!is_subclass_of($class, PageInterface::class)) {
            return ErrorPage::serverError($domain->language, $this->debugDetail('Page class does not implement PageInterface'));
        }

        $paths = $context->service('paths');
        $site = $context->service('site');

        if (!$paths instanceof Paths) {
            return ErrorPage::serverError($domain->language, $this->debugDetail('Paths service missing'));
        }

        $loader = new RegionLoader(
            $paths,
            $site instanceof Config ? $site : null,
            $adminRole,
        );
        $regions = $loader->load($domain);

        try {
            /** @var PageInterface $page */
            $page = new $class($context, $regions, $match->args);
            $result = $page->default();

            return $this->toResponse($result, $domain, $context);
        } catch (\Throwable $e) {
            return ErrorPage::serverError($domain->language, $this->debugDetail($e->getMessage()));
        }
    }

    private function path(DomainContext $domain, string $pathRel, string $classChain, ?string $sessionId = null, ?string $adminRole = null): string
    {
        if ($adminRole !== null) {
            return rtrim($this->appRoot, '/') . '/app/.admin/' . $adminRole . '/algoritm'
                . ($pathRel === '' ? '' : '/' . $pathRel)
                . '/' . $classChain . '.php';
        }

        $domainDir = $sessionId !== null
            ? '/app/.sess_' . $sessionId . '.' . $domain->real
            : '/app/' . $domain->real;

        return rtrim($this->appRoot, '/') . $domainDir
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
            $this->applySkin($domain, $context);

            $doc = new Document($context->meta);
            $doc->lang($domain->language);
            $doc->body()->append($result);

            // Внедряем Flash-сообщения
            $flash = $context->service('flash');
            if ($flash instanceof \Damoony\Core\Flash) {
                $messages = $flash->pull();
                if (!empty($messages)) {
                    $script = new \Damoony\Ui\Element('script');
                    $script->attrs()->add('id', 'flash-data');
                    $script->attrs()->add('type', 'application/json');
                    $script->append(\Damoony\Ui\Text::raw((string) json_encode($messages)));
                    $doc->body()->append($script);
                }
            }

            return Response::html($doc->release());
        }

        return Response::text('', 204);
    }

    private function debugDetail(string $message): string
    {
        return Env::fromServer()->isDev() ? $message : '';
    }

    private function applySkin(DomainContext $domain, RegionContext $context): void
    {
        $site = $context->service('site');
        $allowed = [];
        if ($site instanceof Config) {
            $v = $site->getByKey(['domains_skins_allowed', $domain->real], []);
            if (is_array($v)) {
                $allowed = array_values(array_filter($v, 'is_string'));
            }
        }

        $requested = (string) ($_COOKIE['skin'] ?? '');
        $manager = new \Damoony\Skin\SkinManager(rtrim($this->appRoot, '/') . '/www/media');
        $skin = $manager->resolve($domain->real, $domain->allias, $requested, $allowed);

        $css = $manager->cssUrl($domain->real, $domain->allias, $skin);
        if ($css !== null) {
            $context->meta->addCss($css, 'skin-main');
        }
        $js = $manager->jsUrl($domain->real, $domain->allias, $skin);
        if ($js !== null) {
            $context->meta->addJs($js, 'skin-main');
        }
    }
}
