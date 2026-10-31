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
        $content->append(Builder::h(1)->content('Our Menu'));
        $list = Builder::ul();
        $list->append(Builder::li()->content('Espresso'));
        $list->append(Builder::li()->content('Latte'));
        $list->append(Builder::li()->content('Cappuccino'));
        $content->append($list);
        $content->append(Builder::p()->append(Builder::a('/about', 'btn')->content('Go to About')));
        $content->append(Builder::p()->append(Builder::a('/contacts', 'btn')->content('Go to Contacts')));
        return $content;
    }
}
