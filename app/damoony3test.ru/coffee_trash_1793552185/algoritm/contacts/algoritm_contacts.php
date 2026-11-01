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

        $el = Builder::h(1)->content('Contacts');
        $content->append($el);

        $el = Builder::p()->content('We are located at 123 Coffee Street, Coffeeville. Feel free to visit us or give us a call at (123) 456-7890.');
        $content->append($el);

        $el = Builder::h(2)->content('Opening Hours');
        $content->append($el);

        $el = Builder::p()->content('Monday to Friday: 8 AM - 5 PM<br>Saturday and Sunday: 9 AM - 4 PM');
        $content->append($el);

        $el = Builder::a('/about')->content('Learn more about our coffee shop');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
