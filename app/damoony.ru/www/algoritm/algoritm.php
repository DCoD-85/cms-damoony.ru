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

        $el1 = Builder::h(2)->append(Text::raw(strip_tags('Свяжитесь с нами', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::el('form');
        $el2->attrs()->add('action', '/submit');
        $el2->attrs()->add('method', 'POST');
        $el2->attrs()->classAdd('contact-form');
        $el3 = Builder::el('label');
        $el3->attrs()->add('for', 'name');
        $el3->append(Text::raw(strip_tags('Ваше имя:', '<a><strong>')));
        $el2->append($el3);

        $el4 = Builder::input('text', 'name', '');
        $el4->attrs()->add('placeholder', 'Иван');
        $el4->attrs()->add('required', 'required');
        $el2->append($el4);

        $el5 = Builder::el('label');
        $el5->attrs()->add('for', 'email');
        $el5->append(Text::raw(strip_tags('Email:', '<a><strong>')));
        $el2->append($el5);

        $el6 = Builder::input('email', 'email', '');
        $el6->attrs()->add('required', 'required');
        $el2->append($el6);

        $el7 = Builder::el('label');
        $el7->attrs()->add('for', 'message');
        $el7->append(Text::raw(strip_tags('Сообщение:', '<a><strong>')));
        $el2->append($el7);

        $el8 = Builder::el('textarea');
        $el8->attrs()->add('name', 'message');
        $el8->attrs()->add('rows', '5');
        $el8->append(Text::raw(strip_tags('', '<a><strong>')));
        $el2->append($el8);

        $el9 = Builder::el('label');
        $el9->attrs()->add('for', 'topic');
        $el9->append(Text::raw(strip_tags('Тема:', '<a><strong>')));
        $el2->append($el9);

        $el10 = Builder::el('select');
        $el10->attrs()->add('name', 'topic');
        $el11 = Builder::el('option');
        $el11->attrs()->add('value', 'sales');
        $el11->append(Text::raw(strip_tags('Продажи', '<a><strong>')));
        $el10->append($el11);
        $el12 = Builder::el('option');
        $el12->attrs()->add('value', 'support');
        $el12->append(Text::raw(strip_tags('Поддержка', '<a><strong>')));
        $el10->append($el12);
        $el2->append($el10);

        $el13 = Builder::button('submit');
        $el13->attrs()->classAdd('btn');
        $el13->append(Text::raw(strip_tags('Отправить', '<a><strong>')));
        $el2->append($el13);

        $content->append($el2);

        return $content;
    }
}
