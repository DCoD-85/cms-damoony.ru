<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockglobal_center_doc implements RegionInterface
{
    public function name(): string
    {
        return 'center';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('center-doc');
        $wrap->append(Builder::p()->content('Filler center для алиаса doc'));
        return $wrap;
    }
}
