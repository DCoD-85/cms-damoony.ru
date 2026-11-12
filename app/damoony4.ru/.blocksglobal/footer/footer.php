<?php

declare(strict_types=1);

namespace damoony4_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_footer implements RegionInterface
{
    public function name(): string { return 'footer'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('site-footer');
        $wrap->append(Builder::p()->content($context->lang('block.footer')));        return $wrap;
    }
}
