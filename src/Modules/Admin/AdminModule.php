<?php

declare(strict_types=1);

namespace Damoony\Modules\Admin;

use Damoony\Core\Config;
use Damoony\Core\Module\AccessPolicyProviderInterface;
use Damoony\Core\Module\EntryPointInterface;
use Damoony\Core\Module\ModuleInterface;
use Damoony\Core\Module\PageModuleInterface;
use Damoony\Core\Module\PageRoute;
use Damoony\Core\Security\AccessPolicyInterface;
use Damoony\Modules\AuthAdmin\AdminAccess;

/**
 * Официальная (и пока единственная) админка сервера.
 * Ядро знает только контракт модуля, не «админку».
 */
final class AdminModule implements ModuleInterface, PageModuleInterface, AccessPolicyProviderInterface, EntryPointInterface
{
    private const ROLES = ['superadmin', 'domainadmin'];

    public function __construct(private readonly Config $config)
    {
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
        return filter_var($host, FILTER_VALIDATE_IP) === false ? null : '/app/admin/superadmin/';
    }

    public function resolvePage(string $remainder): ?PageRoute
    {
        $segments = array_values(array_filter(explode('/', trim($remainder, '/')), static fn (string $s): bool => $s !== ''));
        $role = $segments[0] ?? '';
        if (!in_array($role, self::ROLES, true)) {
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
}
