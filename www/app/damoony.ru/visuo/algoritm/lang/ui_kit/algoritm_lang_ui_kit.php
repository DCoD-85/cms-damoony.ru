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

final class algoritm_lang_ui_kit extends Page
{
    protected array $ajaxBlocks = ['top', 'center'];

    protected function content(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el1 = Builder::h(1)->append(Text::raw(strip_tags('Наши лакомства', '<a><strong>')));
        $content->append($el1);

        $el2 = Builder::p()->append(Text::raw(strip_tags('Мы делаем полезные снеки из натурального мяса и овощей. Без химии и консервантов.', '<a><strong>')));
        $content->append($el2);

        $el3 = new Button('Заказать', 'submit');
        $el3->attrs()->classAdd('ui-btn');
        $content->append($el3);

        return $content;
    }
}
