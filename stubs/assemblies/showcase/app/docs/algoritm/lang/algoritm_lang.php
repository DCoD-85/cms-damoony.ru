<?php

declare(strict_types=1);

namespace {{namespace}}\docs;

use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;

final class algoritm_lang extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;

        $wrap = Builder::div('docs-index');
        $wrap->append(Builder::h(1)->content($this->context->lang('docs.title')));
        $wrap->append(Builder::p('docs-intro')->content($this->context->lang('docs.intro')));

        $list = Builder::ul('docs-nav-list');
        foreach (['overview', 'quickstart', 'install', 'cli', 'config', 'projects', 'ui', 'deploy', 'observability'] as $slug) {
            $item = Builder::li();
            $item->append(Builder::a('/' . $lang . '/' . $slug . '/')->content($this->context->lang('docs.nav_' . $slug)));
            $list->append($item);
        }
        $wrap->append($list);

        return $wrap;    }
}
