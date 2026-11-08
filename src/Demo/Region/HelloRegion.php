<?php

declare(strict_types=1);

namespace Damoony\Demo\Region;

use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class HelloRegion implements RegionInterface
{
    public function name(): string
    {
        return 'hello';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('hello');
        $wrap->append(Builder::h(1)->content('Damoony engine'));
        $wrap->append(Builder::p('lead')->content(
            'Домен: ' . $context->domain->domain
            . ' | алиас: ' . $context->domain->allias
            . ' | язык: ' . $context->domain->language,
        ));
        return $wrap;
    }
}
