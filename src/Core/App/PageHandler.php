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
use Damoony\Core\Security\Csrf;

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

        // Глобальная проверка CSRF для всех POST-запросов
        if ($context->request->isPost()) {
            $csrf = $context->service('csrf');
            if ($csrf instanceof Csrf && !$csrf->check($context->request->post)) {
                if ($context->request->isAjax()) {
                    return Response::json(['ok' => false, 'messages' => [['type' => 'error', 'message' => 'CSRF token mismatch']]], 403);
                }
                return Response::text('403 Forbidden: CSRF token mismatch', 403);
            }
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

            return $this->toResponse($result, $domain, $context, $regions, $adminRole, $page->ajaxBlocks(), $debugTime, $page->getScheme());
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
    private function toResponse(Node|Response|null $result, DomainContext $domain, RegionContext $context, RegionRegistry $regions, ?string $adminRole = null, array $ajaxBlocks = ['center'], float $debugTime = 0.0, ?string $customScheme = null): Response
    {
        if ($result instanceof Response) {
            // AJAX: перехватываем редиректы, чтобы JS мог их обработать
            if ($context->request->isAjax() && in_array($result->status, [301, 302, 303, 307, 308], true)) {
                $flash = $context->service('flash');
                $messages = $flash instanceof \Damoony\Core\Flash ? $flash->pull() : [];
                return Response::json([
                    'ok' => true,
                    'messages' => $messages,
                    'blocks' => [],
                    'form' => ['reset' => false, 'keep' => [], 'redirect' => $result->headers['location'] ?? null, 'redirect_time' => 0],
                    'reload' => false,
                ]);
            }
            return $result;
        }

        // AJAX: отдаём блоки и полный каркас как JSON (для Node или null)
        if ($context->request->isAjax()) {
            if ($result instanceof Node) {
                $regions->add(new ContentRegion('center', $result));
            }
            
            // Собираем блоки (для быстрых обновлений)
            $blocks = [];
            foreach ($ajaxBlocks as $blockName) {
                $region = $regions->get($blockName);
                if ($region !== null && $region->enabled()) {
                    $blocks[$blockName] = $region->render($context)->release();
                }
            }

            // Собираем полный Layout (если схема поменялась)
            $layout = null;
            $paths = $context->service('paths');
            if ($paths instanceof Paths) {
                $sid = $context->service('session_id');
                $loader = new SchemeLoader($paths, is_string($sid) ? $sid : null, $adminRole);
                $schemeName = $adminRole !== null ? 'admin' : ($customScheme ?? $domain->scheme);
                try {
                    $manifest = $loader->load($domain, $schemeName);
                    $renderer = new SchemeRenderer();
                    $layout = $renderer->render($manifest, $regions, $context)->release();
                } catch (\RuntimeException $e) {}
            }

            $flash = $context->service('flash');
            $messages = $flash instanceof \Damoony\Core\Flash ? $flash->pull() : [];

            return Response::json([
                'ok' => true,
                'messages' => $messages,
                'blocks' => $blocks,
                'layout' => $layout, // <--- Полный HTML схемы
                'form' => ['reset' => false, 'keep' => [], 'redirect' => null, 'redirect_time' => null],
                'reload' => false,
            ]);
        }

        // SSR: пытаемся загрузить схему; если есть — рендерим (с контентом или без)
        $paths = $context->service('paths');
        $schemeRendered = false;
        if ($paths instanceof Paths) {
            $sid = $context->service('session_id');
            $loader = new SchemeLoader($paths, is_string($sid) ? $sid : null, $adminRole);
            $schemeName = $adminRole !== null ? 'admin' : ($customScheme ?? $domain->scheme);

            try {
                $manifest = $loader->load($domain, $schemeName);
                if ($result instanceof Node) {
                    $regions->add(new ContentRegion('center', $result));
                }
                $renderer = new SchemeRenderer();
                $result = $renderer->render($manifest, $regions, $context);
                $schemeRendered = true;
            } catch (\RuntimeException $e) {
                // В dev SchemeLoader бросит исключение, если схемы нет.
                // В prod вернёт fallback (без исключения).
            }
        }

        // Нет схемы и нет контента → 204
        if (!$schemeRendered && $result === null) {
            return Response::text('', 204);
        }

        // Если схема не отрендерилась, но был Node — оборачиваем в Document как есть
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

    private function debugDetail(string $message): string
    {
        return Env::fromServer()->isDev() ? $message : '';
    }

}
