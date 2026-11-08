<?php

declare(strict_types=1);

namespace Damoony\Demo\Region;

use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class FooterRegion implements RegionInterface
{
    public function name(): string
    {
        return 'footer';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('footer');
        $wrap->append(Builder::p()->content('© ' . date('Y') . ' Damoony'));
        return $wrap;
    }
}
