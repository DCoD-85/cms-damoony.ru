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

        $el = Builder::img('/content/damoony3test.ru/coffee/menu/79c768ffc10268f0.jpg', 'Coffee Menu');
        $content->append($el);

        $el = Builder::p()->content('Explore our wide range of coffee drinks, from classic espressos to unique blends. Each sip is crafted to perfection.');
        $content->append($el);

        $el = Builder::a('/about')->content('About Us');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/contacts')->content('Contact Us');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
