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
        $content->append(Builder::h(1)->content('About Us'));
        $content->append(Builder::p()->content('Founded in 2024'));
        $content->append(Builder::p()->append(Builder::a('/menu', 'btn')->content('Go to Menu')));
        $content->append(Builder::p()->append(Builder::a('/contacts', 'btn')->content('Go to Contacts')));
        return $content;
    }
}
