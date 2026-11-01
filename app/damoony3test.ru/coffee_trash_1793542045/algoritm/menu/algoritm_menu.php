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

        $el = Builder::h(1)->content('Our Menu');
        $content->append($el);

        $el = Builder::p()->content('Explore our diverse menu of coffee and pastries.');
        $content->append($el);

        $el = Builder::img('/media/damoony3test.ru/coffee/menu/956bf1c7026c7142.jpg', 'Coffee menu');
        $content->append($el);

        return $content;
    }
}
