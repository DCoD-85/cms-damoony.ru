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

        $el = Builder::h(1)->content('Menu');
        $content->append($el);

        $el = Builder::p()->content('Welcome to our coffee shop! Explore our extensive menu of delicious coffee drinks.');
        $content->append($el);

        $el = Builder::h(2)->content('Popular Drinks');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Espresso'));
        $el->append(Builder::li()->content('Latte'));
        $el->append(Builder::li()->content('Cappuccino'));
        $el->append(Builder::li()->content('Mocha'));
        $content->append($el);

        $el = Builder::a('/about')->content('Learn more about our coffee shop');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
