<?php

declare(strict_types=1);

namespace {{namespace}};

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_admin_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;

        $menu = Builder::ul('admin-menu');
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/')->content($context->lang('admin.menu_home'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/requests/')->content($context->lang('admin.menu_requests'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $lang . '/installations/')->content($context->lang('admin.menu_installations'))));

        $wrap = Builder::div('dt_inner');
        $wrap->append($menu);

        return $wrap;
    }
}
