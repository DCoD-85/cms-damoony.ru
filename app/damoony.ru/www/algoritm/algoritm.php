<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Ui\Text;
use Damoony\Ui\UrlSanitizer;
use Damoony\Http\Response;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el1 = Builder::section();
        $el1->attrs()->classAdd('hero');
        $el2 = Builder::h(1)->append(Text::raw(strip_tags('Добро пожаловать', '<a><strong>')));
        $el1->append($el2);

        $el3 = Builder::p()->append(Text::raw(strip_tags('Это наш сайт', '<a><strong>')));
        $el1->append($el3);

        $el4 = Builder::div();
        $el4->attrs()->classAdd('cta');
        $el5 = Builder::a('/shop')->append(Text::raw(strip_tags('Купить', '<a><strong>')));
        $el5->attrs()->classAdd('btn');
        $el4->append(Builder::p()->append($el5));

        $el1->append($el4);

        $content->append($el1);

        return $content;
    }
}
