<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_policy_www implements RegionInterface
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
        $wrap = Builder::div('policy-www');
        $wrap->append(Builder::p()->content('Filler policy для алиаса www'));
        return $wrap;
    }
}
