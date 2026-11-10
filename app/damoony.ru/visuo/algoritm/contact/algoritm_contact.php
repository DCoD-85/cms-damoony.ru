<?php

declare(strict_types=1);

namespace damoony_ru\visuo;

use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\Button;
use Damoony\UI\Components\Img;
use Damoony\UI\Components\Link;
use Damoony\UI\Components\Input;
use Damoony\UI\Components\Textarea;
use Damoony\UI\Components\Select;
use Damoony\UI\Components\Option;
use Damoony\UI\Components\Label;
use Damoony\UI\Components\Form;
use Damoony\UI\Components\Table;
use Damoony\UI\Components\Thead;
use Damoony\UI\Components\Tbody;
use Damoony\UI\Components\Tr;
use Damoony\UI\Components\Th;
use Damoony\UI\Components\Td;
use Damoony\UI\Text;
use Damoony\UI\UrlSanitizer;
use Damoony\Core\Http\Response;

final class algoritm_contact extends Page
{
    protected function content(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el1 = Builder::h(1)->append(Text::raw(strip_tags('Контакты (RU)', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::p()->append(Text::raw(strip_tags('Свяжитесь с нами через форму ниже.', '<a><strong>')));
        $content->append($el2);

        $el3 = new Form();
        $el3->action('/');
        $el3->method('POST');
        $el4 = new Input('hidden', '_csrf', '');
        $el4->attrs()->add('value', $this->context->service('csrf')->token());
        $el3->append($el4);
        $el5 = new Label();
        $el5->content(strip_tags('Ваше имя', '<a><strong>'));
        $el3->append($el5);

        $el6 = new Input('text', 'name', '');
        $el6->attrs()->add('placeholder', 'Иван');
        $el3->append($el6);

        $el7 = new Button('Отправить', 'submit');
        $el7->attrs()->classAdd('ui-btn ui-btn-primary');
        $el3->append($el7);

        $content->append($el3);

        return $content;
    }
}
