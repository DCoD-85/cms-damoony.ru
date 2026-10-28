<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_left_www implements RegionInterface
{
    public function name(): string
    {
        return 'left';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('left-www');
        $wrap->append(Builder::p()->content('Filler left для алиаса www'));
        return $wrap;
    }
}
