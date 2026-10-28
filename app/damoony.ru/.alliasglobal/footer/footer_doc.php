<?php

declare(strict_types=1);

namespace damoony_ru;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_footer_doc implements RegionInterface
{
    public function name(): string
    {
        return 'footer';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('footer-doc');
        $wrap->append(Builder::p()->content('Filler footer для алиаса doc'));
        return $wrap;
    }
}
