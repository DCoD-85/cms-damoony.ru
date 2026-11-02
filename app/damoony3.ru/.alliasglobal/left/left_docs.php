<?php

declare(strict_types=1);

namespace damoony3;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_left_docs implements RegionInterface
{
    public function name(): string
    {
        return 'left';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/left.css';
        $context->meta->addCss($cssUrl);

        $content = Builder::div('left');

        $el = Builder::h(3)->content('Навигация');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Главная'));
        $el->append(Builder::li()->content('Документы'));
        $el->append(Builder::li()->content('Теги'));
        $content->append($el);

        $el = Builder::a('/contacts')->content('Связаться с нами');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
