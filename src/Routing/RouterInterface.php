<?php

declare(strict_types=1);

namespace Damoony\Routing;

use Damoony\Domain\DomainContext;

interface RouterInterface
{
    public function match(string $path, DomainContext $domain): RouteMatch;
}