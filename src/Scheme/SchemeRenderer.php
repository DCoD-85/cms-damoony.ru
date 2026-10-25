<?php

declare(strict_types=1);

namespace Damoony\Scheme;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionRegistry;
use Damoony\Ui\Element;

final class SchemeRenderer
{
    public function __construct(
        private readonly RegionRegistry $regions,
    ) {
    }

    public function render(SchemeNode $node, RegionContext $context): Element
    {
        $el = new Element($node->tag);
        if ($node->class !== '') {
            $el->attrs()->classAdd($node->class);
        }
        if ($node->layout !== '') {
            $el->attrs()->add('data-layout', $node->layout);
        }

        $region = $this->regions->get($node->class);
        if ($region !== null && $region->enabled()) {
            $el->append($region->render($context));
            return $el;
        }

        foreach ($node->children as $child) {
            if (!$child->enabled) {
                continue;
            }
            $rendered = $this->render($child, $context);
            if ($child->return) {
                return $rendered;
            }
            $el->append($rendered);
        }

        return $el;
    }
}