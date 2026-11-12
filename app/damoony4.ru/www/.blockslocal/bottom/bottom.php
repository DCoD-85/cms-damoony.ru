<?php

declare(strict_types=1);

namespace damoony4_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_www_bottom implements RegionInterface
{
    public function name(): string { return 'bottom'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('site-local-bottom');
        $wrap->append(Builder::p()->content('это локальный блок bottom'));        return $wrap;
    }
}
