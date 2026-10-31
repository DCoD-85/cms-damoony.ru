<?php

declare(strict_types=1);

namespace damoony3test_ru\coffee;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;

final class algoritm_contacts extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Contact Us');
        $el->attrs()->styleAdd('text-align:center;');
        $content->append($el);

        $el = Builder::p()->content('hello@damoony.coffee');
        $el->attrs()->styleAdd('font-size:1.5em; color:#0066cc; text-align:center;');
        $content->append($el);

        $el = Builder::a('/about')->content('About Us');
        $el->attrs()->classAdd('nav-link');
        $el->attrs()->styleAdd('display:inline-block; margin-right:20px;');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/menu')->content('Our Menu');
        $el->attrs()->classAdd('nav-link');
        $el->attrs()->styleAdd('display:inline-block;');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
