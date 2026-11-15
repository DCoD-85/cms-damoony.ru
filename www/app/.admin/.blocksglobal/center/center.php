<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_center implements RegionInterface
{
    public function name(): string
    {
        return 'center';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        return Builder::div('admin-center-inner');
    }
}
