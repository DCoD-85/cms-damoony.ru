<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_top implements RegionInterface
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
        $wrap = Builder::div('top-wrapper');
        $wrap->append(Builder::h(1)->content('Damoony'));
        $wrap->append(Builder::p()->content('Это базовая шапка сай33та (блок top)'));
        return $wrap;
    }
}