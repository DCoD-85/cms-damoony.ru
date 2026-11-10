<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;
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

        $content = Builder::div('top');

        $el1 = Builder::ul('menu');
        $el2 = new Link('/', 'Главная');
        $el1->append(Builder::li()->append($el2));
        $el3 = new Link('/ui_kit', 'UI Kit');
        $el1->append(Builder::li()->append($el3));
        $el4 = new Link('/contact', 'Контакты');
        $el1->append(Builder::li()->append($el4));
        $content->append($el1);

        return $content;
    }
}
