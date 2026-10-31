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
        $wrap = Builder::div('page');
        $wrap->append(Builder::h(1)->content('About Us'));
        $wrap->append(Builder::p()->content('Founded in 2024'));
        $wrap->append(Builder::a('/menu', 'btn')->content('Go to Menu'));
        $wrap->append(Builder::a('/contacts', 'btn')->content('Go to Contacts'));
        return $wrap;
    }
}
