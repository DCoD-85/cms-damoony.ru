<?php
declare(strict_types=1);
namespace {{namespace}};
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
        $lang = $context->domain->language;

        $wrap = Builder::div('dt_inner');
        $wrap->attrs()->classAdd('dt_footer_inner');
        $wrap->append(Builder::p()->content($context->lang('block.footer.text', ['year' => date('Y')])));

        $nav = Builder::ul('dt_footer_nav');
        $nav->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content($context->lang('menu.home'))));
        $nav->append(Builder::li()->append(Builder::a('/' . $lang . '/about/')->content($context->lang('menu.about'))));
        $nav->append(Builder::li()->append(Builder::a('/' . $lang . '/policy/')->content($context->lang('block.footer.policy'))));
        $wrap->append($nav);

        return $wrap;
    }
}
