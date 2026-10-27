<?php

declare(strict_types=1);

namespace Damoony\Demo\Region;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

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
