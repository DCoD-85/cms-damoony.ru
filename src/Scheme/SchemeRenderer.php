<?php

declare(strict_types=1);

namespace Damoony\Scheme;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionRegistry;
use Damoony\Ui\Element;
use Damoony\Ui\Slot;

final class SchemeRenderer
{
    public function __construct(
        private readonly RegionRegistry $regions,
    ) {
    }

    /**
     * @param array<string, mixed> $skeleton
     */
    public function render(array $skeleton, RegionContext $context): Element
    {
        $root = Element::fromArray($skeleton);
        $this->replaceSlots($root, $context);
        return $root;
    }

    private function replaceSlots(Element $el, RegionContext $context): void
    {
        $newChildren = [];
        
        foreach ($el->children() as $child) {
            if ($child instanceof Slot) {
                $region = $this->regions->get($child->name());
                if ($region !== null && $region->enabled()) {
                    $newChildren[] = $region->render($context);
                }
                // Если региона нет или он выключен — просто не добавляем ничего (слот исчезает)
            } elseif ($child instanceof Element) {
                // Рекурсивно идем внутрь элемента
                $this->replaceSlots($child, $context);
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