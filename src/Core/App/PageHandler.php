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
use Damoony\Core\I18n\Translator;
use Damoony\Core\Module\PageRoute;
use Damoony\Core\Routing\RouteMatch;
use Damoony\Core\Security\Csrf;
use Damoony\UI\Document;
use Damoony\UI\Node;
use Damoony\UI\Page\PageInterface;
use Damoony\UI\Region\ContentRegion;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionLoader;
use Damoony\UI\Region\RegionRegistry;
use Damoony\UI\Response\MinStruct;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

final class PageHandler implements PageHandlerInterface
{
    public function __construct(
        private readonly string $appRoot,
        private readonly DomainResolverInterface $resolver,
    ) {
    }

    public function handle(
        RouteMatch $match,
        RegionContext $context,
        ?PageRoute $moduleRoute = null,
    ): Response {
        $startTime = microtime(true);
        $sessionIdRaw = $context->service('session_id');
        $sessionId = is_string($sessionIdRaw) && $sessionIdRaw !== '' ? $sessionIdRaw : null;
        if ($match->classChain === null || $match->pathRel === null) {
            if ($context->request->isAjax()) {
                $m = new MinStruct();
                $m->error()->message('error', $context->lang('error.404'));
                return $m->toResponse(404);
            }
            return Response::notFound();
        }

        $domain = $context->domain;
        $file = $this->path($domain, $match->pathRel, $match->classChain, $sessionId, $moduleRoute);
        if (!is_file($file)) {
            if ($context->request->isAjax()) {
                $m = new MinStruct();
                $m->error()->message('error', $context->lang('error.404'));
                return $m->toResponse(404);
            }
            return Response::notFound();
        }

        if ($moduleRoute !== null) {
            $class = $moduleRoute->namespace . '\\' . $match->classChain;
        } else {
            $ns = $domain->namespace !== ''
                ? $domain->namespace
                : $this->resolver->namespace($domain->real);
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

        // Глобальная проверка CSRF для всех POST-запросов.
        // Страница может отключить её в своих rules.json: "csrf": false (например, install по токену).
        if ($context->request->isPost() && !$this->csrfDisabled($file)) {
            $csrf = $context->service('csrf');
            if ($csrf instanceof Csrf && !$csrf->check($context->request->post)) {
                if ($context->request->isAjax()) {
                    $m = new MinStruct();
                    $m->error()->message('error', 'CSRF token mismatch');
                    return $m->toResponse(403);
                }
                return Response::text('403 Forbidden: CSRF token mismatch', 403);
            }
        }

        $paths = $context->service('paths');
        $site = $context->service('site');

        if (!$paths instanceof Paths) {
            return ErrorPage::serverError($domain->language, $this->debugDetail('Paths service missing'));
        }

        if ($moduleRoute !== null) {
            $regions = new RegionRegistry();
        } else {
            $loader = new RegionLoader($paths, $site instanceof Config ? $site : null);
            $regions = $loader->load($domain);
        }

        try {
            /** @var PageInterface $page */
            $page = new $class($context, $regions, $match->args);
            $result = $page->default();
            $debugTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->toResponse($result, $domain, $context, $regions, $page->ajaxBlocks(), $debugTime, $page->getScheme(), $moduleRoute);
        } catch (\Throwable $e) {
            return ErrorPage::serverError($domain->language, $this->debugDetail($e->getMessage()));
        }
    }

    private function path(
        DomainContext $domain,
        string $pathRel,
        string $classChain,
        ?string $sessionId = null,
        ?PageRoute $moduleRoute = null,
    ): string {
        if ($moduleRoute !== null) {
            return rtrim($this->appRoot, '/') . '/' . trim($moduleRoute->pagesRoot, '/') . '/algoritm'
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
    private function toResponse(Node|Response|null $result, DomainContext $domain, RegionContext $context, RegionRegistry $regions, array $ajaxBlocks = ['center'], float $debugTime = 0.0, ?string $customScheme = null, ?PageRoute $moduleRoute = null): Response
    {
        if ($result instanceof Response) {
            // AJAX: перехватываем редиректы, чтобы JS мог их обработать
            if ($context->request->isAjax() && in_array($result->status, [301, 302, 303, 307, 308], true)) {
                $flash = $context->service('flash');
                $m = new MinStruct();
                $m->messages($flash instanceof \Damoony\Core\Flash ? $flash->pull() : []);
                $m->form(false, [], $result->headers['location'] ?? null, 0);
                return $m->toResponse();
            }
            return $result;
        }

        // AJAX: отдаём блоки и полный каркас как JSON (для Node или null)
        if ($context->request->isAjax()) {
            $m = new MinStruct();
            if ($result instanceof Node) {
                $regions->add(new ContentRegion('center', $result));
            }

            // Собираем блоки (для быстрых обновлений)
            foreach ($ajaxBlocks as $blockName) {
                $region = $regions->get($blockName);
                if ($region !== null && $region->enabled()) {
                    $m->block($blockName, $region->render($context)->release());
                }
            }

            // Собираем полный Layout (если схема поменялась)
            $paths = $context->service('paths');
            if ($paths instanceof Paths) {
                $sid = $context->service('session_id');
                $loader = new SchemeLoader($paths, is_string($sid) ? $sid : null, $moduleRoute?->role, $moduleRoute?->schemeRoot);
                $schemeName = $moduleRoute !== null
                    ? $moduleRoute->schemeName
                    : ($customScheme ?? $domain->scheme);
                try {
                    $manifest = $loader->load($domain, $schemeName);
                    $renderer = new SchemeRenderer();
                    $m->layout($renderer->render($manifest, $regions, $context)->release());
                } catch (\RuntimeException $e) {
                }
            }

            $flash = $context->service('flash');
            $m->messages($flash instanceof \Damoony\Core\Flash ? $flash->pull() : []);

            return $m->toResponse();
        }

        // SSR: пытаемся загрузить схему; если есть — рендерим (с контентом или без)
        $paths = $context->service('paths');
        $schemeRendered = false;
        if ($paths instanceof Paths) {
            $sid = $context->service('session_id');
            $loader = new SchemeLoader($paths, is_string($sid) ? $sid : null, $moduleRoute?->role, $moduleRoute?->schemeRoot);
            $schemeName = $moduleRoute !== null ? $moduleRoute->schemeName : ($customScheme ?? $domain->scheme);

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
        $translator = $context->service('i18n');
        $doc->lang($translator instanceof Translator ? $translator->current() : $domain->language);
        $doc->body()->append($result);

        // Debug benchmark — единственный источник бейджа (только dev/отладка)
        if (Env::fromServer()->isDev() || isset($_GET['debug'])) {
            $benchmark = new \Damoony\UI\Element('div');
            $benchmark->attrs()->add('id', 'dt_benchmark');
            $benchmark->attrs()->add('class', 'dt_benchmark');
            $benchmark->append(\Damoony\UI\Text::raw('⚡ ' . $debugTime . ' ms'));
            $doc->body()->append($benchmark);
        }

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

    /**
     * Страница может отключить глобальный CSRF в своём rules.json (`"csrf": false`).
     */
    private function csrfDisabled(string $pageFile): bool
    {
        $rulesFile = preg_replace('/\.php$/', '.rules.json', $pageFile);
        if (!is_string($rulesFile) || !is_file($rulesFile)) {
            return false;
        }

        $rules = json_decode((string) file_get_contents($rulesFile), true);

        return is_array($rules) && ($rules['csrf'] ?? true) === false;
    }

}
