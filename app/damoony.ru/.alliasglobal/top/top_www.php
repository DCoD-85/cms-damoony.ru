<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_top_www implements RegionInterface
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
        $wrap = Builder::div('top-www');
        $wrap->append(Builder::p()->content('Filler top для алиаса www'));
        return $wrap;
    }
}
