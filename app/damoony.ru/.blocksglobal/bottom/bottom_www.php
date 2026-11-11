<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_bottom_www implements RegionInterface
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
        $wrap = Builder::div('bottom-www');
        $wrap->append(Builder::p()->content('Filler bottom для алиаса www'));
        return $wrap;
    }
}
