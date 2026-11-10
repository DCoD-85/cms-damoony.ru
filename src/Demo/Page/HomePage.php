<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

class HomePage extends Page
{
    protected function content(): Node|null
    {
        return Builder::div('demo-home')->content('Demo Home Page');
    }
}
