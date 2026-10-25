<?php

declare(strict_types=1);

namespace Damoony\App;

use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Routing\RouteMatch;

interface PageHandlerInterface
{
    public function handle(RouteMatch $match, RegionContext $context): Response;
}