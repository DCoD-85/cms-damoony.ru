<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
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

final class blocksglobal_top implements RegionInterface
{
    public function name(): string
    {
        return 'top';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/top.css';
        $context->meta->addCss($cssUrl);

        $content = Builder::div('dt_' . 'top');

        $el1 = new Img('/media/damoony.ru/visuo/skin/default/img/logo.png', 'Iskra Dog');
        $el1->attrs()->classAdd('logo-img');
        $content->append($el1);

        $el2 = Builder::ul('menu');
        $el3 = new Link('/ru/', 'Главная');
        $el2->append(Builder::li()->append($el3));
        $el4 = new Link('/ru/ui_kit', 'Лакомства');
        $el2->append(Builder::li()->append($el4));
        $el5 = new Link('/ru/dashboard', 'Кабинет');
        $el2->append(Builder::li()->append($el5));
        $content->append($el2);

        $el6 = Builder::ul('menu');
        $el7 = new Link('/ru/', 'RU');
        $el6->append(Builder::li()->append($el7));
        $el8 = new Link('/en/', 'EN');
        $el6->append(Builder::li()->append($el8));
        $el9 = new Link('/zh/', 'ZH');
        $el6->append(Builder::li()->append($el9));
        $content->append($el6);

        return $content;
    }
}
