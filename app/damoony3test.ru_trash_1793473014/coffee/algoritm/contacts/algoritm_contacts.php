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
        $content->append(Builder::h(1)->content('Contact Us'));
        $content->append(Builder::p()->content('hello@damoony.coffee'));
        $content->append(Builder::p()->append(Builder::a('/about', 'btn')->content('Go to About')));
        $content->append(Builder::p()->append(Builder::a('/menu', 'btn')->content('Go to Menu')));
        return $content;
    }
}
