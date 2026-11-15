<?php
declare(strict_types=1);
namespace {{namespace}};
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_{{alias}}_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }
    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('dt_inner');
        $wrap->append(Builder::p()->content($context->lang('block.left')));

        $t = $context->service('i18n');
        $lang = $t instanceof \Damoony\Core\I18n\Translator ? $t->current() : $context->domain->language;
        
        $menu = Builder::ul('dt_menu');
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content($context->lang('menu.home'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/about/')->content($context->lang('menu.about'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/tests/')->content($context->lang('menu.tests'))));
        
        $wrap->append($menu);
        return $wrap;
    }
}