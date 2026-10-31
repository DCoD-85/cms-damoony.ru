<?php

declare(strict_types=1);

namespace damoony3test_ru\coffee;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;

final class algoritm_menu extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Our Menu');
        $el->attrs()->styleAdd('text-align:center; border-bottom: 2px solid #6b3e26;');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Espresso - $3'));
        $el->append(Builder::li()->content('Latte - $4'));
        $el->append(Builder::li()->content('Cappuccino - $4.5'));
        $el->attrs()->classAdd('menu-list');
        $el->attrs()->styleAdd('line-height:2; font-size:1.2em; list-style-type: none;');
        $content->append($el);

        $el = Builder::a('/about')->content('About Us');
        $el->attrs()->styleAdd('display:inline-block; margin-right:20px; color:#6b3e26;');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/contacts')->content('Contacts');
        $el->attrs()->styleAdd('display:inline-block; color:#6b3e26;');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
