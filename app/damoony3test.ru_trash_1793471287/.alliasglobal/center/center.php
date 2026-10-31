<?php

declare(strict_types=1);

namespace damoony3test_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

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
