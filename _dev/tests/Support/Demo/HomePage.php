<?php

declare(strict_types=1);

namespace Damoony\Tests\Support\Demo;

use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Page\Page;

class HomePage extends Page
{
    protected function content(): Node|null
    {
        return Builder::div('demo-home')->content('Demo Home Page');
    }
}
