<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_policy implements RegionInterface
{
    public function name(): string
    {
        return 'policy';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('policy');
        $wrap->append(Builder::p()->content('Блок policy'));
        return $wrap;
    }
}
