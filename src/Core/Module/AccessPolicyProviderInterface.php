<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

use Damoony\Core\Security\AccessPolicyInterface;

/**
 * Модуль, который предоставляет политику доступа для своего mount.
 */
interface AccessPolicyProviderInterface
{
    public function accessPolicy(): AccessPolicyInterface;
}
