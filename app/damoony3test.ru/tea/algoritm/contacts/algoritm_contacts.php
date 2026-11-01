<?php

declare(strict_types=1);

namespace damoony3test_ru\tea;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;

final class algoritm_contacts extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        return $content;
    }
}
