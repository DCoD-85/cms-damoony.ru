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

        $el = Builder::ul('menu');
        $el->append(Builder::li()->append(Builder::a('/')->append(Text::raw(strip_tags('Главная', '<a><strong>')))));
        $el->append(Builder::li()->append(Builder::a('/features')->append(Text::raw(strip_tags('Возможности', '<a><strong>')))));
        $el->append(Builder::li()->append(Builder::a('/pricing')->append(Text::raw(strip_tags('Тарифы', '<a><strong>')))));
        $el->append(Builder::li()->append(Builder::a('/docs')->append(Text::raw(strip_tags('Документация', '<a><strong>')))));
        $el->attrs()->classAdd('main-navigation');
        $content->append($el);

        return $content;
    }
}
