<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_hello implements RegionInterface
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