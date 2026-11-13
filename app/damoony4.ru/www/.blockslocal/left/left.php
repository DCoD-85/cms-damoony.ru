<?php
declare(strict_types=1);
namespace damoony4_ru;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_www_left implements RegionInterface
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
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content('Home')));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/about/')->content('About')));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/tests/')->content('Tests')));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/tests/scheme/')->content('Custom Scheme')));
        
        $wrap->append($menu);
        return $wrap;
    }
}