<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_policy_www implements RegionInterface
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
