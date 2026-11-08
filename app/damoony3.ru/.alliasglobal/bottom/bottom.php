<?php

declare(strict_types=1);

namespace damoony3;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class alliasglobal_bottom implements RegionInterface
{
    public function name(): string
    {
        return 'bottom';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/bottom.css';
        $context->meta->addCss($cssUrl);

        $wrap = Builder::div('bottom');
        $wrap->append(Builder::p()->content('Блок bottom (visual)'));
        return $wrap;
    }
}
