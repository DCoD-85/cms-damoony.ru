<?php

declare(strict_types=1);

namespace Damoony\Core\App;

use Damoony\Core\Config;
use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Domain\DomainResolverInterface;
use Damoony\Core\Env;
use Damoony\Core\Error\ErrorPage;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\Core\Routing\RouteMatch;
use Damoony\UI\Algoritm\PageInterface;
use Damoony\UI\Document;
use Damoony\UI\Node;
use Damoony\UI\Region\ContentRegion;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionLoader;
use Damoony\UI\Region\RegionRegistry;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

final class PageHandler implements PageHandlerInterface
{
    public function __construct(
        private readonly string $appRoot,
        private readonly DomainResolverInterface $resolver,
    ) {
    }

    public function handle(RouteMatch $match, RegionContext $context, ?string $adminRole = null): Response
    {
        $startTime = microtime(true);
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
            $debugTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->toResponse($result, $domain, $context, $regions, $adminRole, $page->ajaxBlocks(), $debugTime);
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

    /**
     * @param list<string> $ajaxBlocks
     */
    private function toResponse(Node|Response|null $result, DomainContext $domain, RegionContext $context, RegionRegistry $regions, ?string $adminRole = null, array $ajaxBlocks = ['center'], float $debugTime = 0.0): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof Node) {
            // AJAX: отдаём блоки как JSON (без skin/document)
            if ($context->request->isAjax()) {
                $regions->add(new ContentRegion('center', $result));
                $blocks = [];

                foreach ($ajaxBlocks as $blockName) {
                    $region = $regions->get($blockName);
                    if ($region !== null && $region->enabled()) {
                        $blocks[$blockName] = $region->render($context)->release();
                    }
                }

                return Response::json([
                    'ok' => true,
                    'blocks' => $blocks,
                    'url' => $context->request->path,
                    'debug_time' => $debugTime,
                ]);
            }

            // Загружаем схему (если есть) и оборачиваем в неё контент
            $paths = $context->service('paths');
            if ($paths instanceof Paths) {
                $sid = $context->service('session_id');
                $loader = new SchemeLoader($paths, is_string($sid) ? $sid : null, $adminRole);
                $schemeName = $adminRole !== null ? 'admin' : $domain->scheme;

                try {
                    $manifest = $loader->load($domain, $schemeName);
                    $regions->add(new ContentRegion('center', $result));
                    $renderer = new SchemeRenderer();
                    $result = $renderer->render($manifest, $regions, $context);
                } catch (\RuntimeException $e) {
                    // В dev SchemeLoader бросит исключение, если схемы нет.
                    // В prod вернёт fallback (без исключения).
                }
            }

            $this->applySkin($domain, $context);

            $doc = new Document($context->meta);
            $doc->lang($domain->language);
            $doc->body()->append($result);

            // Внедряем Flash-сообщения
            $flash = $context->service('flash');
            if ($flash instanceof \Damoony\Core\Flash) {
                $messages = $flash->pull();
                if (!empty($messages)) {
                    $script = new \Damoony\UI\Element('script');
                    $script->attrs()->add('id', 'flash-data');
                    $script->attrs()->add('type', 'application/json');
                    $script->append(\Damoony\UI\Text::raw((string) json_encode($messages)));
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

        $requested = (string) ($context->request->cookie('skin') ?? '');
        $manager = new \Damoony\UI\Skin\SkinManager(rtrim($this->appRoot, '/') . '/www/media');
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
