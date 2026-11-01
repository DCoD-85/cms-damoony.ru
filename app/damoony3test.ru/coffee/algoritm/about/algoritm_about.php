<?php

declare(strict_types=1);

namespace damoony3test_ru\coffee;

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

        $el = Builder::p()->content('Welcome to our cozy coffee shop! We are passionate about providing the perfect cup of coffee and a welcoming atmosphere for our customers.');
        $content->append($el);

        $el = Builder::img('/content/damoony3test.ru/coffee/about/6b4141272519c645.jpg', 'Coffee shop interior');
        $content->append($el);

        $el = Builder::p()->content('Our menu features a variety of handcrafted coffee drinks and delicious snacks. Come and enjoy a break with us!');
        $content->append($el);

        $el = Builder::a('/menu')->content('View Our Menu');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
