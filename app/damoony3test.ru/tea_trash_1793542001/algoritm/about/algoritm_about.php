<?php

declare(strict_types=1);

namespace damoony3test_ru\tea;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;

final class algoritm_about extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('About Us');
        $content->append($el);

        $el = Builder::img('/media/damoony3test.ru/tea/about/230d54d20a120ac8.jpg', 'Tea History');
        $content->append($el);

        $el = Builder::p()->content('Welcome to our tea shop, where we celebrate the rich history and diverse flavors of tea. Our journey began with a passion for exploring different tea cultures and sharing them with our community.');
        $content->append($el);

        $el = Builder::a('/menu')->content('Explore Our Menu');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/contacts')->content('Get in Touch');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
