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
        return Builder::div('page')->content('Страница about');
    }
}
