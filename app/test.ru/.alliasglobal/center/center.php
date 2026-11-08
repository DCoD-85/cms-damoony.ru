<?php

declare(strict_types=1);

namespace test_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class alliasglobal_center implements RegionInterface
{
    public function name(): string { return 'center'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('center');
        $wrap->append(Builder::p()->content('Добро пожаловать на сайт!'));
        return $wrap;
    }
}
