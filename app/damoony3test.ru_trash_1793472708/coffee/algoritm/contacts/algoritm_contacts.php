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
        $wrap = Builder::div('page');
        $wrap->append(Builder::h(1)->content('Contact Us'));
        $wrap->append(Builder::p()->content('hello@damoony.coffee'));
        $wrap->append(Builder::a('/about', 'btn')->content('Go to About'));
        $wrap->append(Builder::a('/menu', 'btn')->content('Go to Menu'));
        return $wrap;
    }
}
