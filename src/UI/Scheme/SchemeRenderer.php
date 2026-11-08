<?php

declare(strict_types=1);

namespace Damoony\UI\Scheme;

use Damoony\UI\Element;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;
use Damoony\UI\Slot;

final class SchemeRenderer
{
    /**
     * @param array<string, mixed> $skeleton
     */
    public function render(array $skeleton, RegionRegistry $regions, RegionContext $context): Element
    {
        // Мета
        if (isset($skeleton['meta']) && is_array($skeleton['meta'])) {
            $meta = $skeleton['meta'];
            if (isset($meta['title'])) {
                $context->meta->setTitle((string) $meta['title']);
            }
            if (isset($meta['css']) && is_array($meta['css'])) {
                foreach ($meta['css'] as $css) {
                    $context->meta->addCss((string) $css);
                }
            }
            if (isset($meta['js']) && is_array($meta['js'])) {
                foreach ($meta['js'] as $js) {
                    $context->meta->addJs((string) $js);
                }
            }
        }

        $root = Element::fromArray($skeleton);
        $this->replaceSlots($root, $regions, $context);

        return $root;
    }

    private function replaceSlots(Element $el, RegionRegistry $regions, RegionContext $context): void
    {
        $newChildren = [];

        foreach ($el->children() as $child) {
            if ($child instanceof Slot) {
                $region = $regions->get($child->name());
                if ($region !== null && $region->enabled()) {
                    $newChildren[] = $region->render($context);
                }
            } elseif ($child instanceof Element) {
                $this->replaceSlots($child, $regions, $context);
                $newChildren[] = $child;
            } else {
                $newChildren[] = $child;
            }
        }

        $el->clear();
        foreach ($newChildren as $c) {
            $el->append($c);
        }
    }
}
