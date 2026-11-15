<?php
declare(strict_types=1);
namespace {{namespace}};
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_bottom implements RegionInterface
{
    public function name(): string { return 'bottom'; }
    public function enabled(): bool { return true; }
    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('dt_inner');
        $wrap->append(Builder::p()->content($context->lang('block.bottom')));
        return $wrap;
    }
}