<?php

declare(strict_types=1);

namespace damoony_ru\agent;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;
use Damoony\Core\Http\Response;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el1 = Builder::div();
        $el1->attrs()->add('id', 'app');
        $content->append($el1);

        return $content;
    }
}
