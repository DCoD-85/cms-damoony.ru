<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_test_m implements RegionInterface
{
    public function name(): string
    {
        return 'test_m';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('test_m');
        $wrap->append(Builder::p()->content('Блок test_m (mixed)'));
        return $wrap;
    }
}
