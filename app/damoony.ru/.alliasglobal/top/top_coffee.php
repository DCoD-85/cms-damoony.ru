<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_top_coffee implements RegionInterface
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
        $el->append(Builder::li()->append(Builder::a('/')->content('Главная')));
        $el->append(Builder::li()->append(Builder::a('/services')->content('Our Services')));
        $el->append(Builder::li()->append(Builder::a('/cases')->content('Success Stories')));
        $el->append(Builder::li()->append(Builder::a('/contacts')->content('Contact Us')));
        $content->append($el);

        return $content;
    }
}
