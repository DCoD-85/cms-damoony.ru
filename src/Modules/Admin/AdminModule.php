<?php

declare(strict_types=1);

namespace Damoony\Modules\Admin;

use Damoony\Core\Config;
use Damoony\Core\Module\AccessPolicyProviderInterface;
use Damoony\Core\Module\ModuleInterface;
use Damoony\Core\Module\PageModuleInterface;
use Damoony\Core\Security\AccessPolicyInterface;
use Damoony\Modules\AuthAdmin\AdminAccess;

/**
 * Официальная (и пока единственная) админка сервера.
 * Ядро знает только контракт модуля, не «админку».
 */
final class AdminModule implements ModuleInterface, PageModuleInterface, AccessPolicyProviderInterface
{
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
}
