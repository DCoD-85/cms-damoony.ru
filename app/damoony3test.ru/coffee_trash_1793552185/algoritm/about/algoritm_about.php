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

        $el = Builder::p()->content('Welcome to our cozy coffee shop! We are passionate about creating a warm and inviting atmosphere where you can relax and enjoy a great cup of coffee.');
        $content->append($el);

        $el = Builder::p()->content('Our menu features a variety of handcrafted beverages and delicious snacks, all made with the finest ingredients. Come by and experience the perfect blend of comfort and coffee culture.');
        $content->append($el);

        $el = Builder::a('/menu')->content('View Our Menu');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/contacts')->content('Get in Touch');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
