<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\UI\Algoritm\Page;
use Damoony\Core\Http\Response;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_about_about extends Page
{
    protected function content(): Node|Response|null
    {
        return Builder::div('page about')->content('about');
    }
}
