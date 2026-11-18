<?php

declare(strict_types=1);

namespace Damoony\Modules\Admin;

use Damoony\Core\Config;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Core\Module\AccessPolicyProviderInterface;
use Damoony\Core\Module\EntryPointInterface;
use Damoony\Core\Module\ModuleInterface;
use Damoony\Core\Module\PageModuleInterface;
use Damoony\Core\Module\PageRoute;
use Damoony\Core\Module\RequestGuardInterface;
use Damoony\Core\Security\AccessPolicyInterface;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\Modules\AuthAdmin\AdminAccess;

/**
 * Официальная админка сервера: auth-first, роль из серверной сессии.
 */
final class AdminModule implements
    ModuleInterface,
    PageModuleInterface,
    AccessPolicyProviderInterface,
    EntryPointInterface,
    RequestGuardInterface
{
    public const COOKIE = 'ADMIN_SESSID';

    private const ROLES = ['superadmin', 'domainadmin'];
    private const AUTH = 'auth';

    public function __construct(
        private readonly Config $config,
        private readonly ?AdminAuthService $auth = null,
    ) {
    }

    public function id(): string
    {
        return 'admin';
    }

    public function apiVersion(): int
    {
        return ModuleInterface::API_VERSION;
    }

    public function mount(): string
    {
        return '/app/admin';
    }

    public function pagesRoot(): string
    {
        return 'app/.admin';
    }

    public function pagesNamespace(): string
    {
        return 'Damoony\\Admin\\Pages';
    }

    public function accessPolicy(): AccessPolicyInterface
    {
        return AdminAccess::fromConfig($this->config);
    }

    public function entryPathForHost(string $host): ?string
    {
        return filter_var($host, FILTER_VALIDATE_IP) === false ? null : '/app/admin/auth/';
    }

    public function resolvePage(string $remainder): ?PageRoute
    {
        $segments = array_values(array_filter(explode('/', trim($remainder, '/')), static fn (string $s): bool => $s !== ''));
        $role = $segments[0] ?? '';
        if ($role !== self::AUTH && !in_array($role, self::ROLES, true)) {
            return null;
        }

        $path = '/' . implode('/', array_slice($segments, 1));

        return new PageRoute(
            pagesRoot: 'app/.admin/' . $role,
            namespace: 'Damoony\\Admin\\Pages\\' . $role,
            path: $path === '/' ? '/' : $path,
            role: $role,
            schemeRoot: 'app/.admin',
            schemeName: 'admin',
        );
    }

    public function guard(Request $request, string $remainder): ?Response
    {
        if (!$this->hostAllowed($request)) {
            return Response::notFound();
        }
        if ($this->auth === null) {
            return Response::notFound();
        }

        if (PHP_SAPI !== 'cli') {
            header('Cache-Control: no-store, private');
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
        }

        $parts = explode('/', trim($remainder, '/'));
        $role = $parts[0];
        $token = (string) ($request->cookies[self::COOKIE] ?? '');

        // Страницы входа доступны без сессии; logout не должен редиректить.
        if ($role === self::AUTH) {
            if (str_starts_with(trim($remainder, '/'), 'auth/logout')) {
                return null;
            }

            $session = $this->auth->authenticate($token);
            if ($session !== null) {
                return Response::redirect('/app/admin/' . $session['role'] . '/');
            }

            return null;
        }

        $session = $this->auth->authenticate($token);
        if ($session === null) {
            return Response::redirect('/app/admin/auth/');
        }

        if ($role === '' || $role !== $session['role']) {
            return Response::notFound();
        }

        return null;
    }

    private function hostAllowed(Request $request): bool
    {
        $host = strtolower((string) ($request->header('host') ?? $request->server['SERVER_NAME'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $allowed = $this->config->getByKey(['security', 'admin_hosts'], []);
        if (!is_array($allowed)) {
            return false;
        }

        return in_array($host, array_map(static fn ($h): string => strtolower((string) $h), $allowed), true);
    }
}
