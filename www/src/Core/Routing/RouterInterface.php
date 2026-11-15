<?php

declare(strict_types=1);

namespace Damoony\Core\Routing;

use Damoony\Core\Domain\DomainContext;

interface RouterInterface
{
    public function match(string $path, DomainContext $domain, ?string $sessionId = null, ?string $adminRole = null): RouteMatch;
}
