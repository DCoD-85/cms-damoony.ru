<?php
declare(strict_types=1);
namespace {{namespace}}\news;
use Damoony\Core\Http\Response;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_third extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->context->domain->language;

        $wrap = Builder::div('news-article');
        $wrap->append(Builder::h(1)->content($this->context->lang('news.third_title')));
        $wrap->append(Builder::p()->content($this->context->lang('news.third_text')));
        $wrap->append(Builder::p()->append(
            Builder::a('/' . $lang . '/')->content($this->context->lang('news.back')),
        ));

        return $wrap;    }
}
