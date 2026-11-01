<?php

declare(strict_types=1);

namespace damoony3test_ru\tea;

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
        $content->append($el);

        $el = Builder::p()->content('We\'re here to help! Please fill out the form below to get in touch with us.');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Email: info@teashop.com'));
        $el->append(Builder::li()->content('Phone: +123-456-7890'));
        $el->append(Builder::li()->content('Address: 123 Tea Street, Tea Town, Tea Country'));
        $content->append($el);

        $el = Builder::a('/about')->content('Learn more about our tea shop.');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/menu')->content('Check out our delicious menu.');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
