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

        $el1 = Builder::el('form');
        $el1->attrs()->add('action', '/submit');
        $el1->attrs()->add('method', 'POST');
        $el1->attrs()->classAdd('contact-form');
        $el2 = Builder::input('hidden', '_csrf', '');
        $el2->attrs()->add('value', $this->context->service('csrf')->token());
        $el1->append($el2);
        $el3 = Builder::el('label');
        $el3->attrs()->add('for', 'name');
        $el3->append(Text::raw(strip_tags('Имя:', '<a><strong>')));
        $el1->append($el3);

        $el4 = Builder::input('text', 'name', '');
        $el4->attrs()->add('required', 'required');
        $el1->append($el4);

        $el5 = Builder::button('submit');
        $el5->attrs()->classAdd('btn');
        $el5->append(Text::raw(strip_tags('Отправить', '<a><strong>')));
        $el1->append($el5);

        $content->append($el1);

        return $content;
    }
}
