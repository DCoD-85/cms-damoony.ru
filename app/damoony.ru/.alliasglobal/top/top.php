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

final class alliasglobal_top implements RegionInterface
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

        $el1 = Builder::ul('menu');
        $el2 = new Link('/ru/', 'Главная');
        if ($context->request->path === '/ru/' || (substr('/ru/', -1) !== '/' && str_starts_with($context->request->path, '/ru/'))) {
            $el2->attrs()->classAdd('active');
        }
        $el1->append(Builder::li()->append($el2));
        $el3 = new Link('/ru/ui_kit', 'UI Kit');
        if ($context->request->path === '/ru/ui_kit' || (substr('/ru/ui_kit', -1) !== '/' && str_starts_with($context->request->path, '/ru/ui_kit'))) {
            $el3->attrs()->classAdd('active');
        }
        $el1->append(Builder::li()->append($el3));
        $el4 = new Link('/ru/features', 'Возможности');
        if ($context->request->path === '/ru/features' || (substr('/ru/features', -1) !== '/' && str_starts_with($context->request->path, '/ru/features'))) {
            $el4->attrs()->classAdd('active');
        }
        $el1->append(Builder::li()->append($el4));
        $el5 = new Link('/ru/contact', 'Контакты');
        if ($context->request->path === '/ru/contact' || (substr('/ru/contact', -1) !== '/' && str_starts_with($context->request->path, '/ru/contact'))) {
            $el5->attrs()->classAdd('active');
        }
        $el1->append(Builder::li()->append($el5));
        $content->append($el1);

        return $content;
    }
}
