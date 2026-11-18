<?php
declare(strict_types=1);
namespace {{namespace}}\news;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;

        $wrap = Builder::div('news-list-wrap');
        $wrap->append(Builder::h(1)->content($this->context->lang('news.title')));
        $wrap->append(Builder::p('news-intro')->content($this->context->lang('news.intro')));

        $list = Builder::ul('news-list');
        foreach (['first', 'second', 'third'] as $slug) {
            $item = Builder::li('news-item');
            $item->append(Builder::span('news-date')->content($this->context->lang('news.date')));
            $item->append(
                Builder::a('/' . $lang . '/' . $slug . '/')
                    ->content($this->context->lang('news.' . $slug . '_title')),
            );

            $list->append($item);
        }

        $wrap->append($list);

        return $wrap;    }
}
