<?php

declare(strict_types=1);

namespace damoony3test_ru\tea;

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

        $el = Builder::p()->content('Explore our wide range of tea varieties, each crafted to perfection.');
        $content->append($el);

        $el = Builder::h(2)->content('Popular Teas');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Green Tea'));
        $el->append(Builder::li()->content('Black Tea'));
        $el->append(Builder::li()->content('Herbal Tea'));
        $el->append(Builder::li()->content('Oolong Tea'));
        $content->append($el);

        $el = Builder::a('/about')->content('Learn More About Us');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
