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
        $wrap->append(Builder::h(1)->content('About Coffee'));
        $wrap->append(Builder::p()->content('Best coffee in town'));
        return $wrap;
    }
}
