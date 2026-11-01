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

        $el = Builder::p()->content('We are located at 123 Coffee Street, Coffee Town. Feel free to visit us or give us a call at (123) 456-7890.');
        $content->append($el);

        $el = Builder::img('/media/damoony3test.ru/coffee/contacts/60e6e796796ee3b5.jpg', 'Coffee shop interior');
        $content->append($el);

        $el = Builder::a('/about')->content('Learn more about us');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/menu')->content('Check our menu');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
