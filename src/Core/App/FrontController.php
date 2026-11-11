<?php

declare(strict_types=1);

namespace Damoony\Core\App;

use Damoony\Core\Config;
use Damoony\Core\Domain\DomainResolverInterface;
use Damoony\Core\Error\ErrorPage;
use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Core\I18n\LanguageNegotiator;
use Damoony\Core\I18n\Translator;
use Damoony\Core\Routing\RouterInterface;
use Damoony\Core\Security\Csrf;
use Damoony\Data\Auth\AuthInterface;
use Damoony\UI\Meta;
use Damoony\UI\Region\RegionContext;
use Psr\Container\ContainerInterface;

final class FrontController
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly PageHandlerInterface $pages,
    ) {
    }

    public function handle(
        Request $request,
        ?string $sessionId = null,
        ?string $aliasOverride = null,
        ?string $adminRole = null,
        ?\Damoony\Core\Domain\DomainContext $adminDomainContext = null,
        ?object $authOverride = null,
    ): Response {
        $resolver = $this->container->get(DomainResolverInterface::class);
        if (!$resolver instanceof DomainResolverInterface) {
            throw new \RuntimeException('DomainResolverInterface expected');
        }

        $host = '';
        if ($adminDomainContext !== null) {
            $domain = $adminDomainContext;
        } else {
            $host = (string) ($request->header('host') ?? $request->server['SERVER_NAME'] ?? '');
            $domain = $resolver->resolve($host);
        }

        if ($aliasOverride !== null && $aliasOverride !== '' && $aliasOverride !== $domain->allias) {
            $domain = $domain->withAllias($aliasOverride);
        }

        // O12: Если домен/поддомен не зарегистрирован в конфиге -> 404
        if ($domain->real === $host && $domain->allias === '') {
            return ErrorPage::notFound($domain->language);
        }

        // Показываем заглушку только для публичного сайта (не для админки)
        if ($adminRole === null && !$domain->available && !str_starts_with($request->path, '/admin')) {
            $paths = $this->container->has(Paths::class) ? $this->container->get(Paths::class) : null;

            if ($paths instanceof Paths) {
                $stubName = $domain->stub ?: 'maintenance';
                $stubFile = rtrim($paths->root(), '/') . '/stubs/' . $stubName . '.html';

                if (is_file($stubFile)) {
                    return Response::html((string) file_get_contents($stubFile), 503);
                }
            }
            return ErrorPage::maintenance($domain->language);
        }

        $router = $this->container->get(RouterInterface::class);
        if (!$router instanceof RouterInterface) {
            throw new \RuntimeException('RouterInterface expected');
        }

        $match = $router->match($request->path, $domain, $sessionId, $adminRole);
        if (!$match->found) {
            return $match->code === 403
                ? ErrorPage::forbidden($domain->language)
                : ErrorPage::notFound($domain->language);
        }

        if ($authOverride instanceof AuthInterface || $authOverride instanceof \Damoony\Admin\AuthInterface) {
            $auth = $authOverride;
        } else {
            $auth = $this->container->get(AuthInterface::class);
        }
        $csrf = $this->container->get(Csrf::class);
        if (!$csrf instanceof Csrf) {
            throw new \RuntimeException('Csrf expected');
        }

        $paths = $this->container->get(Paths::class);
        if (!$paths instanceof Paths) {
            throw new \RuntimeException('Paths expected');
        }

        $site = $this->container->get('site');
        if (!$site instanceof Config) {
            throw new \RuntimeException('Config expected');
        }

        // O11: Определяем язык (URL -> Cookie -> Конфиг)
        if ($adminRole !== null && isset($match->args['lang'])) {
            $lang = $match->args['lang'];
        } else {
            $negotiator = new LanguageNegotiator($domain->supportedLanguages, $domain->language);
            $lang = $negotiator->resolve($request, $domain);
        }

        $adminConfig = $this->container->has('admin_config') ? $this->container->get('admin_config') : null;
        $fallback = $adminConfig instanceof Config ? (string) $adminConfig->getByKey(['default_lang'], '') : '';
        $fallbackOrNull = $fallback === '' ? null : $fallback;

        $sources = [];
        if ($adminRole !== null) {
            $sources[] = $paths->i18nAdmin();
            $sources[] = $paths->i18nAdminRole($adminRole);
        } else {
            $sources[] = $paths->i18nDomain($domain->real);
            $sources[] = $paths->i18nAllias($domain->real, $domain->allias);
        }

        $translator = new Translator($lang, $fallbackOrNull, $paths->lang(), $sources);

        $meta = new Meta();
        $session = $this->container->has(\Damoony\Core\Session\SessionInterface::class)
            ? $this->container->get(\Damoony\Core\Session\SessionInterface::class) : null;
        $flash = $session instanceof \Damoony\Core\Session\SessionInterface
            ? new \Damoony\Core\Flash($session) : null;

        $db = $this->container->has(\Damoony\Data\Db\Connection::class)
            ? $this->container->get(\Damoony\Data\Db\Connection::class) : null;

        $context = new RegionContext($request, $domain, $meta, [
            'paths' => $paths,
            'site' => $site,
            'auth' => $auth,
            'csrf' => $csrf,
            'i18n' => $translator,
            'session_id' => $sessionId,
            'admin_role' => $adminRole,
            'admin_config' => $adminConfig,
            'flash' => $flash,
            'db' => $db,
        ]);

        return $this->pages->handle($match, $context, $adminRole);
    }
}
