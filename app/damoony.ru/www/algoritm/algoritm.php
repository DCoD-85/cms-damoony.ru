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

        $el1 = Builder::h(1)->append(Text::raw(strip_tags('Наши товары', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::el('table');
        $el2->attrs()->classAdd('data-table');
        $el3 = Builder::el('thead');
        $el4 = Builder::el('tr');
        $el5 = Builder::el('th')->append(Text::raw(strip_tags('ID', '<a><strong>')));
        $el4->append($el5);
        $el6 = Builder::el('th')->append(Text::raw(strip_tags('Название', '<a><strong>')));
        $el4->append($el6);
        $el7 = Builder::el('th')->append(Text::raw(strip_tags('Цена', '<a><strong>')));
        $el4->append($el7);
        $el3->append($el4);
        $el2->append($el3);
        $el8 = Builder::el('tbody');
        $el9 = Builder::el('tr');
        $el10 = Builder::el('td')->append(Text::raw(strip_tags('1', '<a><strong>')));
        $el9->append($el10);
        $el11 = Builder::el('td')->append(Text::raw(strip_tags('Кофе', '<a><strong>')));
        $el9->append($el11);
        $el12 = Builder::el('td')->append(Text::raw(strip_tags('300', '<a><strong>')));
        $el9->append($el12);
        $el8->append($el9);
        $el13 = Builder::el('tr');
        $el14 = Builder::el('td')->append(Text::raw(strip_tags('2', '<a><strong>')));
        $el13->append($el14);
        $el15 = Builder::el('td')->append(Text::raw(strip_tags('Чай', '<a><strong>')));
        $el13->append($el15);
        $el16 = Builder::el('td')->append(Text::raw(strip_tags('200', '<a><strong>')));
        $el13->append($el16);
        $el8->append($el13);
        $el2->append($el8);
        $content->append($el2);

        return $content;
    }
}
