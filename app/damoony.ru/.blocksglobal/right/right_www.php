<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockglobal_right_www implements RegionInterface
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
        $wrap = Builder::div('right-www');
        $wrap->append(Builder::p()->content('Filler right для алиаса www'));
        return $wrap;
    }
}
