<?php

declare(strict_types=1);

namespace Damoony\Core\App;

use Damoony\Core\Http\Response;
use Damoony\Core\Module\PageRoute;
use Damoony\Core\Routing\RouteMatch;
use Damoony\UI\Region\RegionContext;

interface PageHandlerInterface
{
    public function handle(
        RouteMatch $match,
        RegionContext $context,
        ?PageRoute $moduleRoute = null,
    ): Response;
}
