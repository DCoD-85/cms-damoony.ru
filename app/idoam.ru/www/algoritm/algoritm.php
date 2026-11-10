<?php

declare(strict_types=1);

namespace idoam_ru\www;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;
use Damoony\Core\Http\Response;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        $content = Builder::div('page-content');

        return $content;
    }
}
