<?php

declare(strict_types=1);

namespace {{namespace}};

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_developer_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;

        $menu = Builder::ul('developer-menu');
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content($context->lang('developer.menu_lk'))));
        $menu->append(Builder::li()->append(Builder::a('https://persona.' . $context->domain->real . '/' . $lang . '/')->content($context->lang('developer.menu_persona'))));

        $wrap = Builder::div('dt_inner');
        $wrap->append($menu);

        return $wrap;
    }
}
