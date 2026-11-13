<?php
declare(strict_types=1);
namespace damoony4_ru;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_center implements RegionInterface
{
    public function name(): string { return 'center'; }
    public function enabled(): bool { return true; }
    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('dt_inner');
        $wrap->append(Builder::p()->content($context->lang('block.center')));
        return $wrap;
    }
}