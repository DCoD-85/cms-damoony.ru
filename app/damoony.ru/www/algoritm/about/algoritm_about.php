<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class algoritm_about extends Algoritm
{
    public function default(): Node|Response|null
    {
        $container = Builder::div('page-container');
        $container->append(Builder::h(1)->content('Это страница ABOUT'));
        return $container;
    }
}
