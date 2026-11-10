<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

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

        $el1 = Builder::h(1)->append(Text::raw(strip_tags('Добро пожаловать в Visuo', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::p()->append(Text::raw(strip_tags('Это стартовая страница нового проекта. Мы используем Damoony Engine.', '<a><strong>')));
        $content->append($el2);

        $el3 = Builder::button('submit');
        $el3->attrs()->classAdd('ui-btn ui-btn-primary');
        $el3->append(Text::raw(strip_tags('Узнать больше', '<a><strong>')));
        $content->append($el3);

        return $content;
    }
}
