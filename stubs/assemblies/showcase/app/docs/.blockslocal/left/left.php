<?php

declare(strict_types=1);

namespace {{namespace}};

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_docs_left implements RegionInterface
{
    public function name(): string { return 'left'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $lang = $context->domain->language;
        $current = $context->request->path;

        $wrap = Builder::div('dt_inner');
        $menu = Builder::ul('docs-menu');

        foreach (['overview', 'quickstart', 'install', 'cli', 'config', 'projects', 'ui', 'deploy', 'observability'] as $slug) {
            $href = '/' . $lang . '/' . $slug . '/';
            $link = Builder::a($href)->content($context->lang('docs.nav_' . $slug));
            if ($current === $href) {
                $link->attrs()->classAdd('active');
            }
            $menu->append(Builder::li()->append($link));
        }

        $wrap->append($menu);

        return $wrap;
    }
}
