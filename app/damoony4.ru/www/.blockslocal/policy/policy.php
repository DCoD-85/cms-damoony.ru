<?php

declare(strict_types=1);

namespace damoony4_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_www_policy implements RegionInterface
{
    public function name(): string { return 'policy'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('site-local-policy');
        $wrap->append(Builder::p()->content($context->lang('block.policy')));        return $wrap;
    }
}
