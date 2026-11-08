<?php

declare(strict_types=1);

namespace damoony3;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class alliasglobal_policy implements RegionInterface
{
    public function name(): string
    {
        return 'policy';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $cssUrl = '/media/' . $context->domain->real . '/' . $context->domain->allias . '/skin/default/css/policy.css';
        $context->meta->addCss($cssUrl);

        $wrap = Builder::div('policy');
        $wrap->append(Builder::p()->content('Блок policy (visual)'));
        return $wrap;
    }
}
