<?php

declare(strict_types=1);

namespace damoony3;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class alliasglobal_right implements RegionInterface
{
    public function name(): string
    {
        return 'right';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/right.css';
        $context->meta->addCss($cssUrl);

        $wrap = Builder::div('right');
        $wrap->append(Builder::p()->content('Блок right (visual)'));
        return $wrap;
    }
}
