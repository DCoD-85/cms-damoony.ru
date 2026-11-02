<?php

declare(strict_types=1);

namespace damoony3;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_bottom implements RegionInterface
{
    public function name(): string
    {
        return 'bottom';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('bottom');
        $wrap->append(Builder::p()->content('Блок bottom (visual)'));
        return $wrap;
    }
}
