<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class alliasglobal_right implements RegionInterface
{
    public function name(): string
    {
        return 'right';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('right');
        $wrap->append(Builder::p()->content('Блок right'));
        return $wrap;
    }
}
