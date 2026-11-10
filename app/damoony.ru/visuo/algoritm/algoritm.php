<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Text;
use Damoony\Core\Http\Response;

final class algoritm extends Page
{
    protected function content(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? '';

        if ($lang === '') {
            return Response::redirect('/ru/', 302);
        }

        $content = Builder::div('page-content');
        $el1 = Builder::h(1)->append(Text::raw(strip_tags('Damoony Engine (' . $lang . ')', '<a><strong>')));
        $content->append($el1);

        return $content;
    }
}
