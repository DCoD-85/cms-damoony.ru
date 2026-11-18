<?php

declare(strict_types=1);

namespace admin_server;

use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class blocksglobal_top implements RegionInterface
{
    public function name(): string
    {
        return 'top';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        return Builder::div('admin-top');
    }
}
