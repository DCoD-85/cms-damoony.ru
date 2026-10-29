<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class algoritm_about_about extends Algoritm
{
    public function default(): Node|Response|null
    {
        return Builder::div('page about')->content('about');
    }
}
