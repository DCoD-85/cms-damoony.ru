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

        $el = Builder::h(1)->content('Contact Us');
        $content->append($el);

        $el = Builder::p()->content('Feel free to reach out to us for any inquiries.');
        $content->append($el);

        $el = Builder::img('/media/damoony3test.ru/coffee/contacts/a174838a38f4cead.jpg', 'Coffee shop contact information');
        $content->append($el);

        return $content;
    }
}
