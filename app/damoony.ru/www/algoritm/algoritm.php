<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Ui\Text;
use Damoony\Ui\UrlSanitizer;
use Damoony\Http\Response;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el1 = Builder::h(1)->append(Text::raw(strip_tags('Добро пожаловать', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::h(2)->append(Text::raw(strip_tags('Наши контакты 3', '<a><strong>')));
        $content->append($el2);

        $el3 = Builder::el('table');
        $content->append($el3);

        return $content;
    }
}
